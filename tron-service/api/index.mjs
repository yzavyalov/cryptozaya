import express from "express";
import cors from "cors";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

import verifyHmac from "./middlewares/verifyHmac.mjs";

import walletRoutes from "./routes/wallet.mjs";
import sendRoutes from "./routes/send.mjs";
import balanceRoutes from "./routes/balance.mjs";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
dotenv.config({ path: path.resolve(__dirname, "../../.env") });

const app = express();
app.use(express.json());
app.use(cors());

// 🔐 защита ВСЕХ запросов к сервису
app.use(verifyHmac);

// routes
app.use("/wallet", walletRoutes);
app.use("/send", sendRoutes);
app.use("/", balanceRoutes);

const PORT = process.env.API_PORT || 4000;
app.listen(PORT, "127.0.0.1", () => {
    console.log(`🚀 Tron API running on port ${PORT}`);
});

