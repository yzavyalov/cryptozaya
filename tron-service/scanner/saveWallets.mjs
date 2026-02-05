// scanner/saveWallets.js
import fs from "fs";
import path from "path";
import https from "https";
import http from "http";
import dotenv from "dotenv";
import crypto from "crypto";

dotenv.config({ path: path.resolve(process.cwd(), "../.env") });

const APP_URL = process.env.APP_URL;
if (!APP_URL) {
    throw new Error("APP_URL is not defined in .env");
}

const WALLETS_URL = `${APP_URL}/api/internal/tron-wallets`;
const FILE_PATH = path.resolve("./tron_wallets.json");
const LOG_FILE = path.resolve("./scan_logs.log");

const payload = ''; // GET → пустое тело
const signature = crypto
    .createHmac('sha256', process.env.WEBHOOK_SECRET)
    .update(payload)
    .digest('hex');

// -----------------------------
// LOGGING
// -----------------------------
function logToFile(...args) {
    try {
        const timestamp = new Date().toISOString();
        const message = args
            .map(a => (typeof a === "object" ? JSON.stringify(a) : a))
            .join(" ");
        fs.appendFileSync(LOG_FILE, `[${timestamp}] ${message}\n`);
    } catch (err) {
        console.error("Logging failed:", err);
    }
}

// -----------------------------
// ENSURE FILE EXISTS
// -----------------------------
function ensureWalletFile() {
    if (!fs.existsSync(FILE_PATH)) {
        fs.writeFileSync(FILE_PATH, JSON.stringify([], null, 2));
        logToFile("Wallet file created:", FILE_PATH);
    }
}

// -----------------------------
// FETCH FROM API
// -----------------------------
function fetchWallets() {
    return new Promise((resolve, reject) => {
        const lib = WALLETS_URL.startsWith("https") ? https : http;

        logToFile("Fetching wallets from:", WALLETS_URL);

        lib.get(WALLETS_URL, {
            headers: {
                'X-Signature': signature,
            },
        }, res => {
            let data = "";
            logToFile("res.statusCode:", res.statusCode);
            res.on("data", chunk => (data += chunk));

            res.on("end", () => {
                try {
                    if (res.statusCode !== 200) {
                        return reject(
                            new Error(`HTTP ${res.statusCode}: ${data}`)
                        );
                    }

                    const json = JSON.parse(data);
                    if (!Array.isArray(json)) {
                        return reject(new Error("API response is not an array"));
                    }

                    resolve(json);
                } catch (err) {
                    reject(err);
                }
            });
        }).on("error", reject);
    });
}

// -----------------------------
// MAIN FUNCTION
// -----------------------------
export async function fetchWalletsAndSave() {
    try {
        ensureWalletFile();

        const wallets = await fetchWallets();

        fs.writeFileSync(FILE_PATH, JSON.stringify(wallets, null, 2));

        logToFile(`Wallets updated. Count: ${wallets.length}`);
        return wallets.length;
    } catch (err) {
        logToFile("Wallet sync error:", err.message);
        throw err;
    }
}


