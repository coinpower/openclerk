<?php

/**
 * BTC-e balance job.
 */

$exchange = "btce";

// get the relevant address
$q = db()->prepare("SELECT * FROM accounts_btce WHERE user_id=? AND id=?");
$q->execute(array($job['user_id'], $job['arg_id']));
$account = $q->fetch();
if (!$account) {
	throw new JobException("Cannot find a $exchange account " . $job['arg_id'] . " for user " . $job['user_id']);
}

// from btc-e documentation somewhere
function btce_query($key, $secret, $method, array $req = array()) {

	$req['method'] = $method;
	$mt = explode(' ', microtime());
	$req['nonce'] = $mt[1];

	// generate the POST data string
	$post_data = http_build_query($req, '', '&');

	$sign = hash_hmac("sha512", $post_data, $secret);

	// generate the extra headers
	$headers = array(
			'Sign: '.$sign,
			'Key: '.$key,
	);

	// our curl handle (initialize if required)
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; BTCE PHP client; '.php_uname('s').'; PHP/'.phpversion().')');
	curl_setopt($ch, CURLOPT_URL, crypto_wrap_url('https://btc-e.com/tapi/'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

	// run the query
	$res = curl_exec($ch);
	if ($res === false) throw new ExternalAPIException('Could not get reply: '.curl_error($ch));
	$dec = crypto_json_decode($res);
	return $dec;
}

$get_supported_wallets = get_supported_wallets();
$currencies = $get_supported_wallets['btce']; // also supports rur, eur, nvc, trc, ppc, nvc
$btce_info = btce_query($account['api_key'], $account['api_secret'], "getInfo");
if (isset($btce_info['error'])) {
	throw new ExternalAPIException("API returned error: '" . $btce_info['error'] . "'");
}
foreach ($currencies as $currency) {
	crypto_log($exchange . " balance for " . $currency . ": " . $btce_info['return']['funds'][$currency]);
	if (!isset($btce_info['return']['funds'][$currency])) {
		throw new ExternalAPIException("Did not find funds for currency $currency in $exchange");
	}

	$balance = $btce_info['return']['funds'][$currency];
	insert_new_balance($job, $account, $exchange, $currency, $balance);

}
