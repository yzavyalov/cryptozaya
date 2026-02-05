import express from "express";
import { commission } from "../../wallet/commission.mjs";

const router = express.Router();


router.post("/estimate-fee", async (req, res) => {
    try {
        // 1. Получаем данные из тела запроса
        const { token, from, to, amount } = req.body;

        // 2. Проверяем наличие обязательных данных
        if (!token || !from || !to || !amount) {
            return res.status(400).json({ error: "Missing required parameters" });
        }

        // 3. Вызываем функцию расчета (передаем параметры, если commission их принимает)
        const result = await commission(token, from, to, amount);

        // 4. Возвращаем результат в формате JSON
        res.json(result);
    } catch {
        res.status(500).json({ error: "We were unable to calculate the commission." });
    }
});

export default router;
