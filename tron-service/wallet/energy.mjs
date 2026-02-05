await tronWeb.trx.delegateResource(
    amountInSun,         // сколько энергии
    userAddress,         // кому
    "ENERGY",
    serviceAddress       // твой кошелёк
);


await tronWeb.trx.undelegateResource(
    amount,
    userAddress,
    "ENERGY",
    serviceAddress
);
