"""
train_model.py
================
Trains the health-recommendation models used by the BMI Monitor app and
saves everything (models + label encoders) into a single bundle file,
health_model.pkl, that app.py loads at startup.

This is the same RandomForestClassifier approach from the original
notebook (Overall_Recommendation), extended with a few extra models so
the Flask service can return a COMPLETE personalized report (calories,
protein, water, sleep, exercise plan, diet tips) instead of just a
category label -- that's what dashboard.php displays.

Usage:
    python train_model.py --csv /path/to/synthetic_health_recommendation_dataset_10000.csv

Run this once (or whenever you retrain on new data). It writes
health_model.pkl next to this script.
"""

import argparse
import os

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier, RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import LabelEncoder

FEATURE_COLUMNS = [
    "Age",
    "Gender",
    "Height_cm",
    "Weight_kg",
    "BMI",
    "Activity_Level",
    "Goal",
]

# Targets we predict from the same feature set. Classifiers for
# categorical/short-text targets, regressors for numeric targets.
CLASSIFICATION_TARGETS = ["Overall_Recommendation", "Exercise_Plan", "Diet_Recommendation"]
REGRESSION_TARGETS = ["Recommended_Calories", "Protein_g_per_day", "Water_L_per_day"]
# NOTE: Sleep_Hours is excluded on purpose -- in this dataset it's random
# noise (7/8/9h, uncorrelated with any feature; R^2 ~ 0), so a model would
# just be memorizing noise. app.py returns a fixed "7-9 hours" guideline
# instead of a fake data-driven number.

INPUT_CATEGORICALS = ["Gender", "Activity_Level", "Goal"]


def train(csv_path: str, out_path: str) -> None:
    df = pd.read_csv(csv_path)

    encoders = {}
    working = df.copy()

    # Encode the categorical *inputs* (Gender, Activity_Level, Goal) once,
    # shared by every model below.
    for col in INPUT_CATEGORICALS:
        le = LabelEncoder()
        working[col] = le.fit_transform(working[col])
        encoders[col] = le

    X = working[FEATURE_COLUMNS]

    classifiers = {}
    for target in CLASSIFICATION_TARGETS:
        le = LabelEncoder()
        y = le.fit_transform(working[target])
        encoders[target] = le

        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        clf = RandomForestClassifier(n_estimators=100, max_depth=10, random_state=42, n_jobs=-1)
        clf.fit(X_train, y_train)
        acc = clf.score(X_test, y_test)
        print(f"[classifier] {target}: test accuracy = {acc:.4f}")
        classifiers[target] = clf

    regressors = {}
    for target in REGRESSION_TARGETS:
        y = working[target]
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        reg = RandomForestRegressor(n_estimators=100, max_depth=10, random_state=42, n_jobs=-1)
        reg.fit(X_train, y_train)
        score = reg.score(X_test, y_test)
        print(f"[regressor]  {target}: test R^2 = {score:.4f}")
        regressors[target] = reg

    bundle = {
        "feature_columns": FEATURE_COLUMNS,
        "input_categoricals": INPUT_CATEGORICALS,
        "encoders": encoders,
        "classifiers": classifiers,
        "regressors": regressors,
    }

    joblib.dump(bundle, out_path, compress=3)
    print(f"\nSaved model bundle -> {out_path}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    default_csv = os.path.join(
        os.path.dirname(__file__), "synthetic_health_recommendation_dataset_10000.csv"
    )
    parser.add_argument("--csv", default=default_csv)
    parser.add_argument(
        "--out", default=os.path.join(os.path.dirname(__file__), "health_model.pkl")
    )
    args = parser.parse_args()
    train(args.csv, args.out)
