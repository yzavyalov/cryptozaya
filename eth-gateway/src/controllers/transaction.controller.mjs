import {
    sendNativeTransaction,
    getTransactionByHash
} from "../services/transaction.service.mjs";

import { ethers } from "ethers";

const provider = new ethers.JsonRpcProvider(process.env.ETH_RPC_URL);

function isValidTxHash(hash) {
    return /^0x([A-Fa-f0-9]{64})$/.test(hash);
}

export async function transactionRoutes(app) {

    // 🚀 ОТПРАВКА ТРАНЗАКЦИИ
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

    // 🔍 ПОЛУЧЕНИЕ ТРАНЗАКЦИИ
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

    // 💰 ESTIMATE FEE (НОВОЕ)
    app.post("/estimate-fee", async (request, reply) => {
        try {
            const { from, to, value, data } = request.body;

            if (!to) {
                reply.code(400);
                return {
                    ok: false,
                    error: "Field 'to' is required"
                };
            }

            const tx = {
                from: from || undefined,
                to,
                value: value !== undefined && value !== null && value !== ""
                    ? ethers.parseEther(value.toString())
                    : undefined,
                data: data || "0x"
            };

            request.log.info({ tx }, "Estimate fee request payload");

            const feeData = await provider.getFeeData();

            const maxFeePerGas = feeData?.maxFeePerGas ?? feeData?.gasPrice;
            const maxPriorityFeePerGas = feeData?.maxPriorityFeePerGas ?? null;

            if (!maxFeePerGas) {
                reply.code(400);
                return {
                    ok: false,
                    error: "Provider did not return maxFeePerGas or gasPrice"
                };
            }

            let gasLimit;

            // native ETH transfer
            if ((!data || data === "0x") && tx.value !== undefined) {
                gasLimit = 21000n;
            } else {
                gasLimit = await provider.estimateGas(tx);
            }

            const estimatedFee = gasLimit * maxFeePerGas;

            return {
                ok: true,
                data: {
                    gasLimit: gasLimit.toString(),
                    maxFeePerGas: maxFeePerGas.toString(),
                    maxPriorityFeePerGas: maxPriorityFeePerGas ? maxPriorityFeePerGas.toString() : null,
                    estimatedFee: estimatedFee.toString(),
                    estimatedFeeEth: ethers.formatEther(estimatedFee)
                }
            };

        } catch (error) {
            request.log.error({
                message: error?.message,
                shortMessage: error?.shortMessage,
                reason: error?.reason,
                code: error?.code,
                info: error?.info,
                stack: error?.stack
            }, "Estimate fee failed");

            reply.code(400);

            return {
                ok: false,
                error:
                    error?.shortMessage ||
                    error?.reason ||
                    error?.message ||
                    "Failed to estimate fee"
            };
        }
    });
}
