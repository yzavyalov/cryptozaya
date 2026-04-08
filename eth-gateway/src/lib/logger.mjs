import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

// Получаем путь к текущему файлу
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const logDir = path.resolve(__dirname, "../../logs");
const logFile = path.join(logDir, "eth-gateway.log");

if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
}

export function logToFile(...args) {
    const time = new Date().toISOString();

    const parts = args.map((arg) => {
        if (typeof arg === "string") {
            return arg;
        }

        try {
            return JSON.stringify(arg, null, 2);
        } catch {
            return String(arg);
        }
    });

    const line = `[${time}] ${parts.join(" ")}\n`;

    fs.appendFileSync(logFile, line, "utf8");
}
