Wallet: 

{
  "xpub": "xpub6FM5tZ13hXDoHesNxQHBRoh1K4Sxh5o14Z5xf1c8fxunsB6zjdsRDDJPGVMuDUrfwJCV6zdogRBfgAtnKQudSCb14zuR6riym2QjTGGR3xJ",
  "mnemonic": "rifle industry permit embark festival install amateur echo dinner gate issue scissors month question unaware army bless absorb mask boss public connect they pet"
}

Moneda: 

{
  "balance": {
    "accountBalance": "100000",
    "availableBalance": "100000"
  },
  "active": true,
  "frozen": false,
  "currency": "VC_USD",
  "accountingCurrency": "EUR",
  "id": "61aa3939f1c9d0c060b2a1b9"
}

Cuentas:

{
  "currency": "VC_USD",
  "active": true,
  "balance": {
    "accountBalance": "0",
    "availableBalance": "0"
  },
  "frozen": false,
  "customerId": "61aa3b619c27cf81fda42938",
  "accountingCurrency": "EUR",
  "id": "61aa3b609c27cf7ef0a42937"
}

{
  "currency": "VC_USD",
  "active": true,
  "balance": {
    "accountBalance": "0",
    "availableBalance": "0"
  },
  "frozen": false,
  "customerId": "61aa3b80cb68d45c21b07380",
  "accountingCurrency": "EUR",
  "id": "61aa3b80cb68d46775b0737f"
}

Ponzi contract: 0x2aaaC8cFbe04fBB13be3F50f64Cb592616280917



----------------
ALTER TABLE `users` ADD `level` INT NOT NULL DEFAULT '1' AFTER `admin`;

Agregar tabla transfers

ALTER TABLE `stack` ADD `showcredit` BOOLEAN NOT NULL DEFAULT TRUE AFTER `simulation`, ADD `displaycredit` INT NOT NULL DEFAULT '0' AFTER `showcredit`;



ALTER TABLE `investment` CHANGE `balance` `balance` DOUBLE NOT NULL DEFAULT '0', CHANGE `mount` `mount` FLOAT NOT NULL;

ALTER TABLE `stack` CHANGE `credits` `credits` DOUBLE NOT NULL DEFAULT '0', CHANGE `displaycredit` `displaycredit` DOUBLE NOT NULL DEFAULT '0';

ALTER TABLE `wallets` CHANGE `credits` `credits` DOUBLE NOT NULL DEFAULT '0';

ALTER TABLE `orders` CHANGE `credit` `credit` DOUBLE NOT NULL DEFAULT '0', CHANGE `debit` `debit` DOUBLE NOT NULL DEFAULT '0';

Agregar tabla personalinfo

ALTER TABLE `users` ADD `referred` INT NULL AFTER `level`;
ALTER TABLE `users` ADD `referredcode` VARCHAR(255) NULL AFTER `referred`;

ALTER TABLE `users` ADD `lastcheck` TIMESTAMP NULL AFTER `referredcode`;

agregar tabla notifications

Agregar conf
agregar tabla refrewards