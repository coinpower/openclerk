<?php

/**
 * Summary job: convert all cryptocurrencies to EUR (via BTC) using Crypto-Trade, and add any USD balances.
 */

// get last value of all BTC
$q = db()->prepare("SELECT * FROM summary_instances WHERE summary_type=? AND user_id=? AND is_recent=1");
$q->execute(array("crypto2btc", $job['user_id']));
if ($balance = $q->fetch()) {

	// BTC is converted at Vircurex last sell rate
	// fail if there is no current rate (otherwise there is no point of this job, we don't want erraneous zero balances)
	$q = db()->prepare("SELECT * FROM ticker WHERE exchange=:exchange AND currency1=:currency1 AND currency2=:currency2 AND is_recent=1");
	$q->execute(array(
		"exchange" => "crypto-trade",
		"currency1" => "eur",
		"currency2" => "btc",
	));
	if ($ticker = $q->fetch()) {
		$total += $balance['balance'] * $ticker['sell'];
	} else {
		throw new JobException("There is no recent ticker balance for eur/btc on crypto-trade - cannot convert");
	}

}

// add total USD balances
$q = db()->prepare("SELECT * FROM summary_instances WHERE summary_type=? AND user_id=? AND is_recent=1");
$q->execute(array("totaleur", $job['user_id']));
if ($balance = $q->fetch()) {
	$total += $balance['balance'];
}

crypto_log("Total converted EUR Crypto-Trade balance for user " . $job['user_id'] . ": " . $total);
