import { JsonRpcProvider } from "ethers";
import dotenv from "dotenv";
import path from "path";

dotenv.config({ path: path.resolve(process.cwd(), ".env") });

if (!process.env.ETH_RPC_URL) {
    throw new Error("ETH_RPC_URL is not set in .env");
}

let provider = null;

export function getProvider() {
    if (!provider) {
        provider = new JsonRpcProvider(process.env.ETH_RPC_URL);
    }

    return provider;
}
