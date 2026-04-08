import fs from "fs";
import path from "path";

const STATE_FILE = path.resolve(process.cwd(), "scanner_state.json");

function ensureStateFile() {
    if (!fs.existsSync(STATE_FILE)) {
        fs.writeFileSync(
            STATE_FILE,
            JSON.stringify(
                {
                    lastBlock: 0,
                    processedEvents: {}
                },
                null,
                2
            )
        );
    }
}

export function loadScannerState() {
    ensureStateFile();

    try {
        const raw = fs.readFileSync(STATE_FILE, "utf8");
        const parsed = JSON.parse(raw);

        return {
            lastBlock: Number(parsed.lastBlock || 0),
            processedEvents: parsed.processedEvents || {}
        };
    } catch {
        return {
            lastBlock: 0,
            processedEvents: {}
        };
    }
}

export function saveScannerState(state) {
    ensureStateFile();

    fs.writeFileSync(STATE_FILE, JSON.stringify(state, null, 2));
}

export function makeEventKey(payload) {
    return [
        payload.txid,
        payload.type,
        String(payload.from || "").toLowerCase(),
        String(payload.to || "").toLowerCase(),
        String(payload.amount || ""),
        String(payload.token_address || "").toLowerCase()
    ].join("|");
}

export function hasProcessedEvent(state, payload) {
    const key = makeEventKey(payload);
    return Boolean(state.processedEvents[key]);
}

export function markEventProcessed(state, payload, blockNumber) {
    const key = makeEventKey(payload);

    state.processedEvents[key] = {
        block: blockNumber,
        processedAt: new Date().toISOString()
    };
}

export function cleanupProcessedEvents(state, keepFromBlock) {
    const nextProcessed = {};

    for (const [key, value] of Object.entries(state.processedEvents || {})) {
        if (Number(value.block || 0) >= keepFromBlock) {
            nextProcessed[key] = value;
        }
    }

    state.processedEvents = nextProcessed;
}
