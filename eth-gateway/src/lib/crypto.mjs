import crypto from "crypto";
import dotenv from "dotenv";
import path from "path";

dotenv.config({ path: path.resolve(process.cwd(), ".env") });

if (!process.env.ENCRYPT_KEY) {
    throw new Error("ENCRYPT_KEY is not set in .env");
}

const KEY = crypto.createHash("sha256")
    .update(process.env.ENCRYPT_KEY)
    .digest();

const ALGO = "aes-256-gcm";

export const WEBHOOK_URL = `${process.env.APP_URL}/api/crypto/webhook`;
export const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;

/**
 * Шифрование приватного ключа
 * @param {string} pk
 * @returns {{iv:string, tag:string, content:string}}
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
 * @param {{iv:string, tag:string, content:string}} enc
 * @returns {string}
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

    if (typeof encrypted === "string") {
        try {
            encObject = JSON.parse(encrypted);
        } catch {
            throw new Error("Encrypted private key is not valid JSON");
        }
    } else {
        encObject = encrypted;
    }

    if (!encObject.iv || !encObject.tag || !encObject.content) {
        throw new Error("Encrypted private key has invalid structure");
    }

    const privateKey = decryptPrivateKey(encObject)?.trim();

    if (!/^(0x)?[0-9a-fA-F]{64}$/.test(privateKey)) {
        throw new Error("Invalid Ethereum private key after decryption");
    }

    return privateKey.startsWith("0x") ? privateKey : `0x${privateKey}`;
}
