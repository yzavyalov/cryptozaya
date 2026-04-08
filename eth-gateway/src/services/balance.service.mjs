import { formatEther, getAddress } from "ethers";
import { getProvider } from "../lib/provider.mjs";
import { logToFile } from "../lib/logger.mjs";

export async function getBalance(address) {
    logToFile("=== getBalance START ===", { address });

    try {
        const provider = getProvider();
        const normalizedAddress = getAddress(address);
        const balanceWei = await provider.getBalance(normalizedAddress);

        const result = {
            address: normalizedAddress,
            balance_wei: balanceWei.toString(),
            balance_eth: formatEther(balanceWei)
        };

        logToFile("Balance result:", result);
        logToFile("=== getBalance END ===\n");

        return result;
    } catch (err) {
        logToFile("getBalance error:", err.message || err);
        throw err;
    }
}
