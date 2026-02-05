import crypto from "crypto";
import fs from "fs";
import path from "path";

// Путь к файлу логов
const logFile = path.join(process.cwd(), "send_token.log");

// Функция для записи логов в файл с таймстампом
function logToFile(...args) {
    const timestamp = new Date().toISOString();
    const message = args.map(a => (typeof a === "object" ? JSON.stringify(a) : a)).join(" ");
    fs.appendFileSync(logFile, `[${timestamp}] ${message}\n`);
}

export default function verifyHmac(req, res, next) {
    let signature, timestamp, data;

    logToFile("=== verifyHmac START ===");
    logToFile("Method:", req.method);

    if (req.method === "GET") {
        timestamp = req.query.timestamp;
        data = req.query.data || "";
        signature = req.headers["x-signature"] || req.query.signature;
    } else {
        timestamp = req.body.timestamp;
        data = JSON.stringify(req.body || {});
        signature = req.headers["x-signature"]; // ⬅️ ВАЖНО
    }

    if (!timestamp || !signature) {
        logToFile("Missing signature or timestamp!");
        return res.status(401).json({ error: "Missing signature" });
    }

    const secret = process.env.WEBHOOK_SECRET;

    const expected = crypto
        .createHmac("sha256", secret)
        .update(
            req.method === "GET"
                ? timestamp + data
                : data
        )
        .digest("hex");

    if (signature !== expected) {
        logToFile("Signature mismatch!");
        return res.status(401).json({ error: "Invalid signature" });
    }

    logToFile("Signature verified successfully ✅");
    logToFile("=== verifyHmac END ===\n");

    next();
}
