import path from "path";
import fs from "fs";
import { TOKENS } from "../shared/tokens.mjs";
import { getTronWeb } from "../shared/tronWebClient.mjs";

const __dirname = path.dirname(new URL(import.meta.url).pathname);
const logFile = path.resolve(__dirname, "../comission_token.log");

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


// ... existing code ...
export async function commission(token, from, to, amount) {
    logToFile("--- Commission calculation started ---");
    logToFile(`Params: token=${token}, from=${from}, to=${to}, amount=${amount}`);

    try {
        if (!token || !amount || !from || !to) {
            logToFile("Error: Missing required parameters");
            throw new Error("Missing required parameters");
        }

        const tokenSymbol = token.toUpperCase();
        const tokenInfo = TOKENS[tokenSymbol];

        if (tokenSymbol !== 'TRX' && !tokenInfo) {
            logToFile(`Error: Unknown token ${tokenSymbol}`);
            throw new Error("Unknown token");
        }

        const base58 = from.startsWith("41") ? tronWeb.address.fromHex(from) : from;
        logToFile(`Using address: ${base58}`);

        // 1. Получаем ресурсы аккаунта
        const accountResource = await tronWeb.trx.getAccountResources(base58);
        logToFile("Account resources fetched");

        // 2. Получаем параметры сети (цены)
        const chainParams = await tronWeb.trx.getChainParameters();
        const energyPrice = (chainParams.find(p => p.key === 'getEnergyFee')?.value || 420) / 1_000_000;
        const bandwidthPrice = (chainParams.find(p => p.key === 'getTransactionFee')?.value || 1000) / 1_000_000;

        logToFile(`Prices: Energy=${energyPrice} TRX, Bandwidth=${bandwidthPrice} TRX`);

        // 3. Свободные ресурсы
        const freeBandwidth = (accountResource.FreeNetLimit || 0) - (accountResource.FreeNetUsed || 0);
        const freeEnergy = (accountResource.EnergyLimit || 0) - (accountResource.EnergyUsed || 0);
        logToFile(`Available: Free Bandwidth=${freeBandwidth}, Free Energy=${freeEnergy}`);

        let energyUsed = 0;
        let bandwidthUsed = 0;

        if (tokenSymbol === 'TRX') {
            bandwidthUsed = 267; // Средний размер TRX транзакции
            energyUsed = 0;
            logToFile("Type: TRX transfer");
        } else {
            logToFile(`Type: TRC20 transfer (${tokenSymbol})`);
            const trigger = await tronWeb.transactionBuilder.triggerSmartContract(
                tokenInfo.address,
                'transfer(address,uint256)',
                {},
                [
                    { type: 'address', value: to },
                    { type: 'uint256', value: Math.floor(amount * 10 ** tokenInfo.decimals) }
                ],
                base58
            );

            energyUsed = trigger.energy_used || 32000;
            bandwidthUsed = (trigger.transaction.raw_data_hex.length / 2) + 64;
        }

        logToFile(`Estimated usage: Energy=${energyUsed}, Bandwidth=${bandwidthUsed}`);

        // 4. Расчет стоимости
        const paidBandwidth = Math.max(0, bandwidthUsed - freeBandwidth);
        const paidEnergy = Math.max(0, energyUsed - freeEnergy);

        const networkFeeTRX = (paidBandwidth * bandwidthPrice) + (paidEnergy * energyPrice);
        const serviceFeeTRX = 0.2; // Ваша фиксированная комиссия
        const totalFee = networkFeeTRX + serviceFeeTRX;

        const result = {
            network_fee: Number(networkFeeTRX.toFixed(6)),
            service_fee: serviceFeeTRX,
            total_fee: Number(totalFee.toFixed(6)),
            fee_currency: 'TRX',
            energy_used: energyUsed,
            bandwidth_used: bandwidthUsed,
            free_energy: freeEnergy,
            free_bandwidth: freeBandwidth
        };

        logToFile("Result calculated:", result);
        return result;

    } catch (e) {
        logToFile("CRITICAL ERROR in commission function:", e.message);
        console.error(e);
        throw e; // Пробрасываем ошибку дальше
    }
}
