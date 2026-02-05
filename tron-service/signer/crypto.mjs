import crypto from "crypto";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

// Получаем путь к текущему файлу
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Загружаем .env из корня проекта
dotenv.config({ path: path.resolve(__dirname, "../../.env") });

// Проверяем, что ключ задан
if (!process.env.ENCRYPT_KEY) {
    throw new Error("ENCRYPT_KEY is not set in .env");
}

const KEY = crypto.createHash("sha256")
    .update(process.env.ENCRYPT_KEY)
    .digest();

const ALGO = "aes-256-gcm";

// Для примера: webhook переменные
export const WEBHOOK_URL = `${process.env.APP_URL}/api/crypto/webhook`;
export const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;

/**
 * Шифрование приватного ключа
 * @param {string} pk - приватный ключ
 * @returns {Object} { iv, tag, content }
 */
export function encryptPrivateKey(pk) {
    const iv = crypto.randomBytes(12);
    const cipher = crypto.createCipheriv(ALGO, KEY, iv);

    let encrypted = cipher.update(pk, "utf8", "hex");
    encrypted += cipher.final("hex");

    return {
        iv: iv.toString("hex"),
        tag: cipher.getAuthTag().toString("hex"),
        content: encrypted
    };
}

/**
 * Дешифрование приватного ключа
 * @param {Object} enc - объект { iv, tag, content }
 * @returns {string} расшифрованный приватный ключ
 */
export function decryptPrivateKey(enc) {
    const decipher = crypto.createDecipheriv(
        ALGO,
        KEY,
        Buffer.from(enc.iv, "hex")
    );

    decipher.setAuthTag(Buffer.from(enc.tag, "hex"));

    let decrypted = decipher.update(enc.content, "hex", "utf8");
    decrypted += decipher.final("utf8");

    return decrypted;
}


export function decryptPrivateKeySafe(encrypted) {
    if (!encrypted) {
        throw new Error("Encrypted private key is missing");
    }

    let encObject;

    // 🔥 если пришла JSON-строка
    if (typeof encrypted === "string") {
        try {
            encObject = JSON.parse(encrypted);
        } catch (e) {
            throw new Error("Encrypted private key is not valid JSON");
        }
    } else {
        encObject = encrypted;
    }

    // базовая валидация структуры
    if (!encObject.iv || !encObject.content) {
        throw new Error("Encrypted private key has invalid structure");
    }

    const privateKey = decryptPrivateKey(encObject)?.trim();

    // 🔐 жёсткая проверка приватного ключа TRON
    if (!/^[0-9a-fA-F]{64}$/.test(privateKey)) {
        throw new Error("Invalid private key after decryption");
    }

    return privateKey;
}
