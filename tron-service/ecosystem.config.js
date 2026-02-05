module.exports = {
    apps: [
        {
            name: "tron-api",
            script: "./api/index.mjs",
            exec_mode: "fork",
            instances: 1,
            autorestart: true,
            max_memory_restart: "300M",
            interpreter: "node",
            // node_args: "--experimental-specifier-resolution=node"
        },
        {
            name: "tron-scanner",
            script: "./scanner/index.mjs",
            exec_mode: "fork",
            instances: 1,
            autorestart: true,
            max_memory_restart: "300M",
            interpreter: "node",
            // node_args: "--experimental-specifier-resolution=node"
        }
    ]
};
