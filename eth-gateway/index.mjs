import { config as loadEnv } from "dotenv";
import { JsonRpcProvider } from "ethers";
import { buildApp } from "./src/app.mjs";

loadEnv();

const PORT = Number(process.env.PORT || 3010);
const HOST = process.env.HOST || "0.0.0.0";
const ETH_RPC_URL = process.env.ETH_RPC_URL;
const ETH_CHAIN_ID = Number(process.env.ETH_CHAIN_ID || 11155111);
const ETH_NETWORK = process.env.ETH_NETWORK || "sepolia";

if (!ETH_RPC_URL) {
    console.error("ETH_RPC_URL is not set in .env");
    process.exit(1);
}

const provider = new JsonRpcProvider(ETH_RPC_URL);
const app = await buildApp();

app.decorate("ethProvider", provider);
app.decorate("ethConfig", {
    chainId: ETH_CHAIN_ID,
    network: ETH_NETWORK
});

app.get("/", async () => {
    return {
        ok: true,
        service: "eth-gateway",
        network: ETH_NETWORK,
        expectedChainId: ETH_CHAIN_ID,
        message: "Ethereum gateway is running"
    };
});

app.get("/health", async () => {
    try {
        const [network, blockNumber] = await Promise.all([
            provider.getNetwork(),
            provider.getBlockNumber()
        ]);

        const actualChainId = Number(network.chainId);
        const chainMatches = actualChainId === ETH_CHAIN_ID;

        return {
            ok: chainMatches,
            service: "eth-gateway",
            network: ETH_NETWORK,
            expectedChainId: ETH_CHAIN_ID,
            actualChainId,
            chainMatches,
            networkName: network.name,
            blockNumber
        };
    } catch (error) {
        app.log.error(error);

        return {
            ok: false,
            service: "eth-gateway",
            network: ETH_NETWORK,
            error: error.message
        };
    }
});

try {
    await app.listen({
        port: PORT,
        host: HOST
    });

    app.log.info(`ETH gateway started on http://${HOST}:${PORT}`);
} catch (error) {
    app.log.error(error);
    process.exit(1);
}
