import { walletRoutes } from "./controllers/wallet.controller.mjs";
import { balanceRoutes } from "./controllers/balance.controller.mjs";
import { transactionRoutes } from "./controllers/transaction.controller.mjs";

export async function registerRoutes(app) {
    await walletRoutes(app);
    await balanceRoutes(app);
    await transactionRoutes(app);
}
