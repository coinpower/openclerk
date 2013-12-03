<?php

/**
 * Get current Novacoin block number. Used to deduct unconfirmed transactions
 * when retrieving Feathercoin balances.
 */

$block = crypto_get_contents(crypto_wrap_url(get_site_config('nvc_block_url')));
if (!is_numeric($block) || !$block) {
	throw new ExternalAPIException("Novacoin block number was not numeric: " . htmlspecialchars($block));
}

crypto_log("Current Novacoin block number: " . number_format($block));

// disable old instances
$q = db()->prepare("UPDATE novacoin_blocks SET is_recent=0 WHERE is_recent=1");
$q->execute();

// we have a balance; update the database
$q = db()->prepare("INSERT INTO novacoin_blocks SET blockcount=:count,is_recent=1");
$q->execute(array(
	"count" => $block,
));
crypto_log("Inserted new novacoin_blocks id=" . db()->lastInsertId());
