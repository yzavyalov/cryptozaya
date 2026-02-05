import express from "express";
import { createWallet } from "../../wallet/wallet.mjs";
import { getBalance } from "../../wallet/wallet.mjs";

const router = express.Router();

router.get("/create", async (req, res) => {
    try {
        const wallet = await createWallet();
        res.json(wallet);
    } catch {
        res.status(500).json({ error: "Wallet creation failed" });
    }
});

router.get("/:address/balances", async (req, res) => {
    try {
        const { address } = req.params;
        const balance = await getBalance(address);
        res.json(balance);
    } catch (err) {
        console.error(err);
        res.status(500).json({ error: "We couldn't show balance" });
    }
});

export default router;
