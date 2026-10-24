# ML Recommendation Service

A small Flask microservice that wraps the trained scikit-learn models and
serves them over HTTP, so the PHP app (`api/bmi.php`) can get a
personalised recommendation for every BMI reading without needing PHP to
run Python/scikit-learn directly.

```
Browser/ESP32 → api/bmi.php (PHP) → cURL POST → ml_service/app.py (Flask, port 5001)
                                                        ↓
                                              health_model.pkl (RandomForest)
```

## What it predicts

Given `age, gender, height_cm, weight_kg, activity_level, goal`, `/predict`
returns:

| Field | Model |
|---|---|
| `overall_recommendation` | RandomForestClassifier |
| `exercise_plan` | RandomForestClassifier |
| `diet_recommendation` | RandomForestClassifier |
| `recommended_calories` | RandomForestRegressor |
| `protein_g_per_day` | RandomForestRegressor |
| `water_l_per_day` | RandomForestRegressor |
| `sleep_hours` | fixed "7-9" guideline (see note below) |
| `bmi`, `bmi_category` | computed directly, not modeled |

**Note on Sleep_Hours:** in `synthetic_health_recommendation_dataset_10000.csv`,
`Sleep_Hours` is uniformly random (7/8/9h) and uncorrelated with every other
column — a model trained on it just memorizes noise (R² ≈ 0). Rather than
present a falsely-precise ML number, `app.py` returns the standard "7-9
hours" sleep guideline instead.

## Setup

```bash
cd ml_service
pip install -r requirements.txt --break-system-packages   # or use a venv

# Train once (needs the CSV in this folder, or pass --csv /path/to/it)
python train_model.py

# Run the service (defaults to http://127.0.0.1:5001)
python app.py
```

Leave this running in its own terminal/window alongside XAMPP (Apache +
MySQL). `includes/config.php` in the main app points at
`http://127.0.0.1:5001` via the `ML_SERVICE_URL` constant — change that if
you run the service somewhere else.

If this service is down, `api/bmi.php` still saves the BMI reading — it
just leaves the recommendation fields `null` and the dashboard shows a
message instead of the AI Recommendation card. It never blocks a reading
from being logged.

## Retraining

Re-run `python train_model.py` any time you update the CSV — it overwrites
`health_model.pkl`. Restart `app.py` afterwards to load the new bundle.

## API

### GET /health
```json
{"status": "ok"}
```

### POST /predict
Request:
```json
{
  "age": 22,
  "gender": "Male",
  "height_cm": 170,
  "weight_kg": 92,
  "activity_level": "Sedentary",
  "goal": "Lose Weight"
}
```
Response:
```json
{
  "success": true,
  "bmi": 31.83,
  "bmi_category": "Obese",
  "overall_recommendation": "Calorie deficit + cardio + strength",
  "exercise_plan": "30 min cardio + 3x strength/week",
  "diet_recommendation": "High protein, high fibre, low sugar",
  "recommended_calories": 1738,
  "protein_g_per_day": 138,
  "water_l_per_day": 3.2,
  "sleep_hours": "7-9"
}
```
`gender` must be `Male`/`Female`, `activity_level` one of `Sedentary` /
`Lightly Active` / `Moderately Active` / `Very Active`, `goal` one of
`Lose Weight` / `Maintain Weight` / `Gain Weight` — same categories used
throughout the dataset and the PHP app's dropdowns.
