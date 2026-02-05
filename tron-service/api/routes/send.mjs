import express from "express";
import { sendTransaction } from "../../signer/send.mjs";

const router = express.Router();

router.post("/:token", async (req, res) => {
    try {
        const result = await sendTransaction(req.params.token, req.body);
        res.json(result);
    } catch {
        res.status(500).json({ error: "Transaction failed" });
    }
});

export default router;
