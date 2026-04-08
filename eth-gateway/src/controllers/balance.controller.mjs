import { getBalance } from "../services/balance.service.mjs";
import { getTokenBalance } from "../services/token.service.mjs";

export async function balanceRoutes(app) {

    // ---------------- ETH ----------------
    app.get("/balance/:address", async (request, reply) => {
        try {
            const { address } = request.params;
            const result = await getBalance(address);

            return {
                ok: true,
                data: {
                    token: "ETH",
                    ...result
                }
            };
        } catch (error) {
            request.log.error(error);
            reply.code(400);

            return {
                ok: false,
                error: error.message
            };
        }
    });

    // ---------------- ERC20 ----------------
    app.get("/token-balance/:token/:address", async (request, reply) => {
        try {
            const { token, address } = request.params;

            const result = await getTokenBalance(token, address);

            return {
                ok: true,
                data: result
            };
        } catch (error) {
            request.log.error(error);
            reply.code(400);

            return {
                ok: false,
                error: error.message
            };
        }
    });
}
