import { Wallet } from "ethers";
import { encryptPrivateKey } from "../lib/crypto.mjs";
import { logToFile } from "../lib/logger.mjs";

export async function createWallet() {
    logToFile("=== createWallet START ===");

    try {
        const account = Wallet.createRandom();

        const wallet = {
            address: account.address,
            publicKey: account.signingKey.publicKey,
            hex: account.address,
            encrypted_private_key: encryptPrivateKey(account.privateKey)
        };

        logToFile("Created wallet:", {
            address: wallet.address,
            publicKey: wallet.publicKey,
            hex: wallet.hex
        });
        logToFile("=== createWallet END ===\n");

        return wallet;
    } catch (err) {
        logToFile("createWallet error:", err.message || err);
        throw err;
    }
}
