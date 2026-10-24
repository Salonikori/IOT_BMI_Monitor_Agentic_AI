# IoT BMI Monitor with Agentic AI

An IoT-based health tracking system that combines ESP32 sensors, web technologies, machine learning, and an agentic AI assistant to record BMI measurements, visualize historical trends, and provide personalized health and fitness insights.

## Overview

The **IoT BMI Monitor** is a full-stack project developed to explore how connected hardware, machine learning, and AI agents can work together in a health-tracking application.

The system collects height and weight measurements, calculates Body Mass Index (BMI), stores readings in a database, and displays the results through a web dashboard. A machine learning service generates fitness-related recommendations, while an AI agent can analyze historical readings, retrieve user information, and respond to questions using tool calls.

## Key Features

- **IoT-Based Measurement:** ESP32 integration with an ultrasonic sensor and load cell for height and weight measurement.
- **BMI Tracking:** Calculate BMI and maintain a history of recorded measurements.
- **User Authentication:** Registration, login, session management, and role-based admin access.
- **Interactive Dashboard:** View recent readings, historical records, and BMI trends.
- **Machine Learning Recommendations:** Generate exercise, diet, calorie, protein, and water-intake suggestions using a trained Random Forest model.
- **Agentic AI Assistant:** Use an LLM with tool calling to retrieve user profiles, analyze BMI history, access ML predictions, and explain trends.
- **Email Reports and Alerts:** Send measurement reports and support configurable notifications.
- **Admin Dashboard:** View user records and manage administrative monitoring tasks.
- **Modular Architecture:** Separate PHP web application, ML microservice, and AI agent service.

## System Architecture

```text
ESP32 + Sensors
       |
       v
PHP Web Application
       |
       +---- MySQL Database
       |
       +---- ML Recommendation Service
       |          Flask | Random Forest
       |
       +---- Agentic AI Service
                  LLM + Tool Calling
                       |
             +---------+---------+
             |         |         |
          User Data  ML Model  Email
             |
             v
      Web Dashboard
      and AI Chat
```

The PHP application handles the web interface and data collection. The ML service produces recommendations from user inputs, while the agentic service coordinates tools to access historical records, obtain predictions, and evaluate trends.

## Technology Stack

| Component               | Technologies                               |
| ----------------------- | ------------------------------------------ |
| Frontend                | HTML, CSS, JavaScript                      |
| Backend                 | PHP                                        |
| Database                | MySQL                                      |
| IoT Hardware            | ESP32, HC-SR04, HX711, Load Cell           |
| Machine Learning        | Python, Flask, scikit-learn, Random Forest |
| Agentic AI              | Python, Flask, Anthropic API, Tool Calling |
| Charts                  | Chart.js                                   |
| Email                   | SMTP, PHPMailer                            |
| Development Environment | XAMPP, Arduino IDE, Python                 |

## Project Structure

```text
bmi-monitor-agentic-ai/
├── admin/                 # Admin dashboard
├── api/                   # PHP API endpoints
├── assets/
│   ├── css/               # Stylesheets
│   └── js/                # Frontend JavaScript
├── includes/              # Shared PHP configuration
├── agent_service/         # Agentic AI microservice
├── ml_service/            # ML prediction service
├── database.sql           # Database schema
├── esp32_bmi_monitor.ino  # ESP32 firmware
├── dashboard.php          # Main dashboard
├── history.php            # Measurement history
├── chat.php               # AI assistant interface
├── login.php              # Login
├── register.php           # Registration
└── README.md
```

## Getting Started

### Prerequisites

Install the following before running the project:

- [XAMPP](https://www.apachefriends.org/) with Apache and MySQL
- [Python](https://www.python.org/) and pip
- [Arduino IDE](https://www.arduino.cc/en/software/) for hardware integration
- An Anthropic API key for the AI agent
- ESP32 hardware and compatible sensors for physical measurements

### 1. Set Up the Web Application

1. Clone the repository.
2. Copy the project folder into the XAMPP `htdocs` directory.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Open phpMyAdmin and import `database.sql` to create the database schema.
5. Configure your local database connection and application settings using the project's secure configuration approach.

### 2. Configure the ML Service

Open a terminal and run:

```bash
cd ml_service
python -m venv .venv
```

Activate the virtual environment and install dependencies:

```bash
# Windows
.venv\Scripts\activate

# macOS / Linux
source .venv/bin/activate
```

```bash
pip install -r requirements.txt
```

If required, train the model using the provided dataset:

```bash
python train_model.py
```

Start the prediction service:

```bash
python app.py
```

The ML service is configured to run locally on port `5001` by default.

### 3. Configure the Agentic AI Service

Open a separate terminal:

```bash
cd agent_service
python -m venv .venv
```

Activate the environment and install the dependencies:

```bash
# Windows
.venv\Scripts\activate

# macOS / Linux
source .venv/bin/activate
```

```bash
pip install -r requirements.txt
```

Create a local `.env` file using `.env.example` as a reference. Configure the required API key, database connection, ML service URL, and optional SMTP settings.

Run the agent database migration if the database has not yet been upgraded:

```bash
mysql -u root -p bmi_monitor < migration_add_agent_tables.sql
```

Start the service:

```bash
python app.py
```

The agent service is configured to run locally on port `5002` by default.

### 4. Launch the Application

Keep XAMPP, the ML service, and the agent service running in their respective terminals.

Open the application in your browser:

```text
http://localhost/bmi_monitor/
```

**Note:** Update configuration paths and service URLs if your local environment differs. Complete the initial database setup and required migrations before testing all features.

## Hardware Setup

The ESP32 firmware supports the following components:

- ESP32 development board
- HC-SR04 ultrasonic distance sensor
- HX711 load-cell amplifier
- Load cell
- Optional I2C LCD

Before uploading the firmware, configure the Wi-Fi credentials locally, set the server endpoint to your computer's local network IP, and calibrate the load cell.

Use appropriate voltage-level protection for the HC-SR04 echo signal when connecting it to a 3.3 V ESP32 GPIO. Validate sensor readings before using them in the application.

Hardware integration is optional when exploring the web application and services independently.

## AI and Machine Learning

The project uses two complementary AI components:

**Machine Learning Service**

A trained Random Forest model produces recommendations based on profile information, activity level, fitness goals, and measurements.

**Agentic AI Service**

An LLM-based agent can invoke defined tools to retrieve user information, inspect historical readings, request ML predictions, and log agent events. It also supports conversational interaction through the application.

The agent uses the ML service rather than independently replacing the underlying prediction model.

## Security and Privacy

- Never commit API keys, SMTP passwords, database credentials, or real Wi-Fi credentials.
- Keep local environment files out of version control.
- Use synthetic or anonymized data in public demonstrations.
- Configure authentication, authorization, and database access appropriately before deployment.
- Do not expose local development services directly to the public internet without suitable security controls.

## Limitations

- Accurate physical measurements depend on sensor placement, calibration, and hardware quality.
- ML recommendations depend on the quality and representativeness of the training data.
- The AI agent requires a valid API key and network access to the configured provider.
- Localhost URLs and XAMPP configuration are intended for development, not production deployment.
- Health-related outputs are informational and educational. They are not medical diagnoses or a substitute for advice from a qualified healthcare professional.

## Future Improvements

- Deploy the application using a production-ready architecture.
- Add automated tests and CI/CD checks.
- Improve sensor calibration and measurement validation.
- Evaluate model performance on representative, ethically sourced datasets.
- Add stronger access controls, audit logging, and privacy safeguards.
- Improve observability and error handling across the microservices.

## Project Context

Developed as a software and AI project during a winter internship period, demonstrating practical integration of web development, IoT hardware, machine learning, and agentic AI.

## License

Choose an appropriate open-source license before distributing the project. If you intend to retain exclusive rights, review the implications of publishing the source code publicly before selecting a license.
