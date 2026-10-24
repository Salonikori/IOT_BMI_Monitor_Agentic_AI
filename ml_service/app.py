"""
app.py
======
Small Flask microservice that wraps the trained health-recommendation
models and exposes them over HTTP as JSON, so the PHP app (which can't
run scikit-learn directly) can call them with a normal cURL request.

PHP side: includes/config.php -> get_ml_recommendation() POSTs to
    ML_SERVICE_URL . '/predict'
and api/bmi.php stores the result alongside each BMI reading.

Run:
    pip install -r requirements.txt
    python train_model.py      # produces health_model.pkl (once)
    python app.py              # serves on http://127.0.0.1:5001

Endpoints:
    GET  /health   -> {"status": "ok"}
    POST /predict  -> body: {age, gender, height_cm, weight_kg,
                              activity_level, goal}
                       returns the full personalized report.
"""

import os

import joblib
import numpy as np
import pandas as pd
from flask import Flask, jsonify, request
from flask_cors import CORS

MODEL_PATH = os.path.join(os.path.dirname(__file__), "health_model.pkl")

app = Flask(__name__)
CORS(app)  # allow the PHP server (different port/host) to call this

_bundle = None  # lazy-loaded model bundle


def load_bundle():
    global _bundle
    if _bundle is None:
        if not os.path.exists(MODEL_PATH):
            raise FileNotFoundError(
                f"{MODEL_PATH} not found. Run `python train_model.py` first."
            )
        _bundle = joblib.load(MODEL_PATH)
    return _bundle


def bmi_category(bmi: float) -> str:
    if bmi < 18.5:
        return "Underweight"
    if bmi < 25:
        return "Normal"
    if bmi < 30:
        return "Overweight"
    return "Obese"


VALID_GENDERS = {"Male", "Female"}
VALID_ACTIVITY = {"Sedentary", "Lightly Active", "Moderately Active", "Very Active"}
VALID_GOALS = {"Lose Weight", "Maintain Weight", "Gain Weight"}


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/predict", methods=["POST"])
def predict():
    bundle = load_bundle()
    data = request.get_json(silent=True) or {}

    try:
        age = float(data["age"])
        gender = str(data["gender"])
        height_cm = float(data["height_cm"])
        weight_kg = float(data["weight_kg"])
        activity_level = str(data["activity_level"])
        goal = str(data["goal"])
    except (KeyError, ValueError, TypeError):
        return jsonify({
            "success": False,
            "message": "Missing/invalid fields. Required: age, gender, height_cm, "
                       "weight_kg, activity_level, goal.",
        }), 400

    if gender not in VALID_GENDERS:
        return jsonify({"success": False, "message": f"gender must be one of {sorted(VALID_GENDERS)}"}), 400
    if activity_level not in VALID_ACTIVITY:
        return jsonify({"success": False, "message": f"activity_level must be one of {sorted(VALID_ACTIVITY)}"}), 400
    if goal not in VALID_GOALS:
        return jsonify({"success": False, "message": f"goal must be one of {sorted(VALID_GOALS)}"}), 400
    if height_cm <= 0:
        return jsonify({"success": False, "message": "height_cm must be > 0"}), 400

    bmi = round(weight_kg / ((height_cm / 100) ** 2), 2)

    encoders = bundle["encoders"]
    row = {
        "Age": age,
        "Gender": encoders["Gender"].transform([gender])[0],
        "Height_cm": height_cm,
        "Weight_kg": weight_kg,
        "BMI": bmi,
        "Activity_Level": encoders["Activity_Level"].transform([activity_level])[0],
        "Goal": encoders["Goal"].transform([goal])[0],
    }
    X = pd.DataFrame([row])[bundle["feature_columns"]]

    result = {
        "success": True,
        "bmi": bmi,
        "bmi_category": bmi_category(bmi),
    }

    for target, clf in bundle["classifiers"].items():
        pred = clf.predict(X)
        label = encoders[target].inverse_transform(pred)[0]
        result[target.lower()] = label

    for target, reg in bundle["regressors"].items():
        pred = float(reg.predict(X)[0])
        result[target.lower()] = round(pred, 1) if "water" in target.lower() else int(round(pred))

    # Sleep_Hours isn't modeled (it's noise in the training data -- see
    # train_model.py) so we give the standard guideline instead of a
    # falsely-precise ML number.
    result["sleep_hours"] = "7-9"

    return jsonify(result)


if __name__ == "__main__":
    load_bundle()  # fail fast at startup if the model hasn't been trained
    app.run(host="127.0.0.1", port=5001, debug=False)
