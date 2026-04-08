import {
    Wallet,
    getAddress,
    parseEther,
    formatEther
} from "ethers";
import { decryptPrivateKeySafe } from "../lib/crypto.mjs";
import { getProvider } from "../lib/provider.mjs";
import { logToFile } from "../lib/logger.mjs";

export async function sendNativeTransaction({
                                                encrypted_private_key,
                                                to,
                                                amount
                                            }) {
    logToFile("=== sendNativeTransaction START ===", {
        to,
        amount
    });

    try {
        if (!encrypted_private_key) {
            throw new Error("encrypted_private_key is required");
        }

        if (!to) {
            throw new Error("to is required");
        }

        if (!amount) {
            throw new Error("amount is required");
        }

        const provider = getProvider();
        const privateKey = decryptPrivateKeySafe(encrypted_private_key);
        const normalizedTo = getAddress(to);

        const wallet = new Wallet(privateKey, provider);

        const tx = await wallet.sendTransaction({
            to: normalizedTo,
            value: parseEther(String(amount))
        });

        const result = {
            hash: tx.hash,
            from: wallet.address,
            to: normalizedTo,
            amount,
            nonce: tx.nonce
        };

        logToFile("Transaction sent:", result);
        logToFile("=== sendNativeTransaction END ===\n");

        return result;
    } catch (err) {
        logToFile("sendNativeTransaction error:", err.message || err);
        throw err;
    }
}

export async function getTransactionByHash(hash) {
    logToFile("=== getTransactionByHash START ===", { hash });

    try {
        if (!hash) {
            throw new Error("hash is required");
        }

        const provider = getProvider();

        const [tx, receipt, currentBlock] = await Promise.all([
            provider.getTransaction(hash),
            provider.getTransactionReceipt(hash),
            provider.getBlockNumber()
        ]);

        if (!tx) {
            const notFoundResult = {
                hash,
                found: false,
                status: "not_found"
            };

            logToFile("Transaction not found:", notFoundResult);
            logToFile("=== getTransactionByHash END ===\n");

            return notFoundResult;
        }

        let confirmations = 0;
        let status = "pending";

        if (receipt?.blockNumber) {
            confirmations = Math.max(currentBlock - receipt.blockNumber + 1, 0);
            status = receipt.status === 1 ? "confirmed" : "failed";
        }

        const result = {
            found: true,
            hash: tx.hash,
            from: tx.from,
            to: tx.to,
            value_wei: tx.value.toString(),
            value_eth: formatEther(tx.value),
            nonce: tx.nonce,
            block_number: receipt?.blockNumber ?? null,
            confirmations,
            status
        };

        logToFile("Transaction result:", result);
        logToFile("=== getTransactionByHash END ===\n");

        return result;
    } catch (err) {
        logToFile("getTransactionByHash error:", err.message || err);
        throw err;
    }
}
