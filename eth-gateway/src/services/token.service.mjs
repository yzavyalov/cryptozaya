import { Contract, isAddress, getAddress } from "ethers";
import { getProvider } from "../lib/provider.mjs";
import { logToFile } from "../lib/logger.mjs";

const ERC20_ABI = [
    "function balanceOf(address owner) view returns (uint256)"
];

const TOKENS = {
    USDT: process.env.ETH_USDT_CONTRACT,
    USDC: process.env.ETH_USDC_CONTRACT
};

export async function getTokenBalance(token, address) {
    logToFile("=== getTokenBalance START ===", { token, address });

    const symbol = token.toUpperCase();

    if (!TOKENS[symbol]) {
        throw new Error(`Unsupported token ${symbol}`);
    }

    if (!isAddress(address)) {
        throw new Error("Invalid wallet address");
    }

    try {
        const provider = getProvider();
        const normalizedAddress = getAddress(address);

        const contract = new Contract(
            TOKENS[symbol],
            ERC20_ABI,
            provider
        );

        const balance = await contract.balanceOf(normalizedAddress);

        const result = {
            token: symbol,
            contract: TOKENS[symbol],
            address: normalizedAddress,
            balance: balance.toString()
        };

        logToFile("Token balance result:", result);
        logToFile("=== getTokenBalance END ===\n");

        return result;

    } catch (err) {
        logToFile("getTokenBalance error:", err.message || err);
        throw err;
    }
}
