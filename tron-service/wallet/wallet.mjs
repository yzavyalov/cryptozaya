import { getTronWeb } from "../shared/tronWebClient.mjs";
import { encryptPrivateKey } from "../signer/crypto.mjs";
import fs from "fs";
import path from "path";
import { TOKENS } from "../shared/tokens.mjs";

const TRC20_ABI = [
    {
        constant: true,
        inputs: [{ name: "_owner", type: "address" }],
        name: "balanceOf",
        outputs: [{ name: "balance", type: "uint256" }],
        type: "function",
    },
];

const logFile = path.resolve("./wallet_token.log");

function logToFile(...args) {
    try {
        const timestamp = new Date().toISOString();
        const message = args.map(a => (typeof a === "object" ? JSON.stringify(a, null, 2) : a)).join(" ");
        fs.appendFileSync(logFile, `[${timestamp}] ${message}\n`);
    } catch (err) {
        console.error("Logging failed:", err);
    }
}

// 🔹 безопасная инициализация tronWeb
const tronWeb = getTronWeb();

/**
 * Создание кошелька
 */
export async function createWallet() {
    logToFile("=== createWallet START ===");

    try {
        const account = tronWeb.utils.accounts.generateAccount();
        const wallet = {
            address: account.address.base58,
            publicKey: account.publicKey,
            hex: account.address.hex,
            encrypted_private_key: encryptPrivateKey(account.privateKey)
        };

        logToFile("Created wallet:", wallet);
        logToFile("=== createWallet END ===\n");
        return wallet;
    } catch (err) {
        logToFile("createWallet error:", err.message || err);
        throw err;
    }
}

/**
 * Получение баланса TRX
 */
export async function getBalance(address) {
    logToFile("=== getBalance START ===", address);

    if (!tronWeb) {
        const err = new Error("TronWeb is not initialized");
        logToFile("getBalance error:", err.message);
        throw err;
    }

    logToFile("tronWeb initialized successfully");

    try {
        if (!address) throw new Error("Empty address");

        const base58 = address.startsWith("41")
            ? tronWeb.address.fromHex(address)
            : address;

        const hex = tronWeb.address.toHex(base58);

        logToFile("address:", address);
        logToFile("base58:", base58);
        logToFile("hex:", hex);

        // ВАЖНО: выставим owner_address для последующих contract.call()
        // (всё равно дополнительно передадим from в call — так надёжнее)
        try {
            tronWeb.setAddress(base58);
        } catch (e) {
            // если tronWeb не поддерживает setAddress в твоей сборке — не критично
            logToFile("tronWeb.setAddress warning:", e.message || e);
        }

        const balances = {};

        // TRX
        balances.TRX = (await tronWeb.trx.getBalance(base58)) / 1e6;

        // TRC20
        for (const [symbol, token] of Object.entries(TOKENS)) {
            if (!token?.address) continue;

            try {
                const contract = await tronWeb.contract(TRC20_ABI, token.address);

                // Передаём base58 в balanceOf и задаём from (owner_address)
                const raw = await contract.balanceOf(base58).call({ from: base58 });

                // raw может быть BigNumber/строка — приводим аккуратно
                const rawStr =
                    typeof raw === "object" && raw?.toString ? raw.toString() : String(raw);

                balances[symbol] = Number(rawStr) / 10 ** Number(token.decimals ?? 6);
            } catch (e) {
                logToFile(
                    `TRC20 balance error for ${symbol}:`,
                    e?.message || e,
                    { tokenAddress: token.address, owner: base58 }
                );
                balances[symbol] = 0;
            }
        }

        logToFile("balances:", balances);

        return {
            address: base58,
            hex,
            balances,
        };
    } catch (err) {
        logToFile("getBalance error:", err.message || err);
        throw err;
    }
}
