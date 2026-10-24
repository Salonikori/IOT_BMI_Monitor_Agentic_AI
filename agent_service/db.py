"""
db.py
=====
Tiny MySQL connection helper for the agent service. Reads the same
credentials PHP uses (see includes/config.php), pulled from environment
variables so both sides can share one .env if you want.
"""

import os

import mysql.connector
from mysql.connector import Error as MySQLError


def get_connection():
    return mysql.connector.connect(
        host=os.environ.get("DB_HOST", "localhost"),
        user=os.environ.get("DB_USER", "root"),
        password=os.environ.get("DB_PASS", ""),
        database=os.environ.get("DB_NAME", "bmi_monitor"),
        autocommit=True,
    )


def fetch_one(query: str, params: tuple = ()):
    conn = get_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(query, params)
        return cur.fetchone()
    finally:
        conn.close()


def fetch_all(query: str, params: tuple = ()):
    conn = get_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(query, params)
        return cur.fetchall()
    finally:
        conn.close()


def execute(query: str, params: tuple = ()):
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(query, params)
        return cur.lastrowid
    finally:
        conn.close()
