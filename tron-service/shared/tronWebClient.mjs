import {TronWeb} from "tronweb";

const FULL_HOST = "https://api.trongrid.io";

let tronWebRead = null;

function requireTronGridKey()
{
    const key = process.env.TRON_GRID_KEY;
    if (!key) {
        // Важно: пусть это ловится выше и превращается в 500/503, а не "тихо null"
        throw new Error("TRON_GRID_KEY is not set");
    }
    return key;
}

/**
 * Singleton для запросов "на чтение" (балансы/блоки/ресурсы).
 */
export function getTronWeb() {
    if (tronWebRead) return tronWebRead;

    const apiKey = requireTronGridKey();

    tronWebRead = new TronWeb({
        fullHost: FULL_HOST,
        headers: { "TRON-PRO-API-KEY": apiKey },
    });

    return tronWebRead;
}

/**
 * Инстанс для операций, где нужен privateKey (подпись/отправка транзакции).
 * Не кэшируем, потому что privateKey разный.
 */
export function createTronWebWithPrivateKey(privateKey) {
    const apiKey = requireTronGridKey();

    return new TronWeb({
        fullHost: FULL_HOST,
        privateKey,
        headers: { "TRON-PRO-API-KEY": apiKey },
    });
}
