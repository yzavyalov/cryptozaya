import {
    sendNativeTransaction,
    getTransactionByHash
} from "../services/transaction.service.mjs";

function isValidTxHash(hash) {
    return /^0x([A-Fa-f0-9]{64})$/.test(hash);
}

export async function transactionRoutes(app) {
    app.post("/send", async (request, reply) => {
        try {
            const result = await sendNativeTransaction(request.body);

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

    app.get("/tx/:hash", async (request, reply) => {
        try {
            const { hash } = request.params;

            if (!isValidTxHash(hash)) {
                reply.code(400);
                return {
                    ok: false,
                    error: "Invalid transaction hash format"
                };
            }

            const result = await getTransactionByHash(hash);

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
