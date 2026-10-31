import os
import sys

import paho.mqtt.publish as mqtt_publish
import pika
import psycopg2
import pymysql
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from pymongo import MongoClient

app = FastAPI(title="Python API", version="1.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class Message(BaseModel):
    message: str


def _check(fn):
    try:
        return {"status": "ok", **fn()}
    except Exception as e:  # noqa: BLE001
        return {"status": "error", "message": str(e)}


def check_mysql():
    conn = pymysql.connect(
        host=os.environ["MYSQL_HOST"],
        user=os.environ["MYSQL_USER"],
        password=os.environ["MYSQL_PASSWORD"],
        database=os.environ["MYSQL_DATABASE"],
        connect_timeout=5,
    )
    with conn.cursor() as cur:
        cur.execute("SELECT VERSION()")
        version = cur.fetchone()[0]
    conn.close()
    return {"version": version}


def check_postgres():
    conn = psycopg2.connect(
        host=os.environ["PG_HOST"],
        user=os.environ["PG_USER"],
        password=os.environ["PG_PASSWORD"],
        dbname=os.environ["PG_DATABASE"],
        connect_timeout=5,
    )
    with conn.cursor() as cur:
        cur.execute("SELECT version()")
        version = cur.fetchone()[0]
    conn.close()
    return {"version": version}


def check_mongo():
    client = MongoClient(os.environ["MONGO_URI"], serverSelectionTimeoutMS=5000)
    info = client.server_info()
    client.close()
    return {"version": info["version"]}


def rabbit_connection():
    creds = pika.PlainCredentials(os.environ["RABBITMQ_USER"], os.environ["RABBITMQ_PASS"])
    params = pika.ConnectionParameters(
        host=os.environ["RABBITMQ_HOST"],
        port=int(os.environ["RABBITMQ_PORT"]),
        credentials=creds,
    )
    return pika.BlockingConnection(params)


def check_rabbit():
    conn = rabbit_connection()
    conn.close()
    return {"connected": True}


def check_mqtt():
    mqtt_publish.single(
        "demo/health",
        payload="ping from python-api",
        hostname=os.environ["MQTT_HOST"],
        port=int(os.environ["MQTT_PORT"]),
    )
    return {"published": True}


@app.get("/")
def root():
    return {
        "service": "python-api",
        "python": sys.version.split()[0],
        "docs": "/docs",
        "routes": ["GET /health", "POST /rabbitmq/publish", "POST /mqtt/publish"],
    }


@app.get("/health")
def health():
    return {
        "mysql": _check(check_mysql),
        "postgres": _check(check_postgres),
        "mongodb": _check(check_mongo),
        "rabbitmq": _check(check_rabbit),
        "mqtt": _check(check_mqtt),
    }


@app.post("/rabbitmq/publish")
def rabbit_publish(body: Message):
    try:
        conn = rabbit_connection()
        ch = conn.channel()
        ch.queue_declare(queue="demo", durable=True)
        ch.basic_publish(
            exchange="",
            routing_key="demo",
            body=body.message.encode(),
            properties=pika.BasicProperties(delivery_mode=2),
        )
        conn.close()
        return {"published": True, "queue": "demo", "message": body.message, "by": "python-api"}
    except Exception as e:  # noqa: BLE001
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/mqtt/publish")
def mqtt_pub(body: Message):
    try:
        mqtt_publish.single(
            "demo/python",
            payload=body.message,
            hostname=os.environ["MQTT_HOST"],
            port=int(os.environ["MQTT_PORT"]),
        )
        return {"published": True, "topic": "demo/python", "message": body.message}
    except Exception as e:  # noqa: BLE001
        raise HTTPException(status_code=500, detail=str(e))
