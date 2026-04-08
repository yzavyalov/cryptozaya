import { createWallet } from "../services/wallet.service.mjs";

export async function walletRoutes(app) {
    app.post("/wallet/create", async (request, reply) => {
        try {
            const wallet = await createWallet();

            return {
                ok: true,
                data: wallet
            };
        } catch (error) {
            request.log.error(error);
            reply.code(500);

            return {
                ok: false,
                error: error.message
            };
        }
    });
}
