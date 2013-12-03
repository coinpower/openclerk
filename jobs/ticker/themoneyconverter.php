<?php

/**
 * TheMoneyConverter ticker job.
 */

/*
	"Can I Use The Exchange Rate Feeds For My Business Or Website?

	The simple answer is - Yes; However, there are conditions.

	This service is intended for everyone and so we don't want any selfish individuals treating it as their own personal data feed and soaking up the bandwidth for their own purposes. Show a little courtesy and common sense and the feeds can be kept running at a reasonable cost to ourselves. For example, if you have a website or smartphone application that requires the feeds then please don't sent all of your users directly to this site. Instead, download the data to your own server and get the users to retrieve the data from your own site.

	We reserve the right to block any users that we consider to be exhibiting unreasonable use.

	You should also note that we cannot guarantee the accuracy of the exchange rates or the format of the rss feeds and so if you are using them for a business that relies on them for your day-to-day operations then you should really consider switching to a more reliable (i.e. paid-for) service. In short, you use this data at your own risk and if you lose money as a result of using these feeds then it's your own fault.

	Oh, and one other thing... please provide a courtesy link to this website from within a prominent page on your own website/application to ensure that we get credited with providing a valuable service for the benefit of the search engine algorithms of this world. This will enable us to keep the service free of charge for everyone."
*/

function map_currency($c) {
	if ($c == "rur") {
		return "rub";
	}
	return $c;
}
$feeds = array(
	"http://themoneyconverter.com/rss-feed/USD/rss.xml" => array(
		array('usd', 'eur'),
		array('usd', 'nzd'),
		array('usd', 'gbp'),
		array('usd', 'aud'),
		array('usd', 'cad'),
	),
	// TODO maybe remove this and switch existing tickers over in database
	"http://themoneyconverter.com/rss-feed/RUB/rss.xml" => array(
		array('rur', 'usd'),
	),
);

$exchange = "themoneyconverter";

// now go through each relevant feed
$errors = array();
$first = true;
foreach ($feeds as $url => $pairs) {
	if (!$first) {
		set_time_limit(get_site_config('sleep_themoneyconverter_ticker') * 2);
		sleep(get_site_config('sleep_themoneyconverter_ticker'));
	}
	$first = false;

	// get the feed
	$feed = crypto_get_contents(crypto_wrap_url($url));

	// load as XML
	$xml = new SimpleXMLElement($feed);

	// find relevant pairs
	foreach ($pairs as $pair) {
		$rate = get_rate($xml, map_currency($pair[1]), map_currency($pair[0]));
		if ($rate === false) {
			$error = "Feed '$url': Could not find any " . map_currency($pair[1]) . "/" . map_currency($pair[0]) . " (translated from " . $pair[1] . "/" . $pair[0] . ")";
			crypto_log($error);
			$errors[] = $error;
		} else if ($rate == 0) {
			$error = "Feed '$url': " . map_currency($pair[1]) . "/" . map_currency($pair[0]) . " (translated from " . $pair[1] . "/" . $pair[0] . ") rate was zero";
			crypto_log($error);
			$errors[] = $error;
		} else {
			insert_new_ticker_last_trade($exchange, $pair[0], $pair[1], 1.0 / $rate /* need to flip it over */, $job['id']);
		}
	}

}

if ($errors) {
	throw new ExternalAPIException(number_format($errors) . " errors occured while processing feeds: first " . $errors[0]);
}

function get_rate($xml, $cur1, $cur2) {
	$nodes = $xml->xpath("/rss/channel/item/title[text()='" . strtoupper($cur1) . "/" . strtoupper($cur2) . "']/../description");
	if (!$nodes) {
		return false;
	}
	$description = (string) $nodes[0];
	// otherwise, it's the value after the =
	$matches = array();
	if (preg_match("#1 [^=]+ = ([0-9\.]+) #i", $description, $matches)) {
		return $matches[1];
	} else {
		return false;
	}
}

function insert_new_ticker_last_trade($exchange, $currency1, $currency2, $last_trade, $job_id) {
	crypto_log("$exchange value for $currency1/$currency2: $last_trade");

	// update old recent values
	$q = db()->prepare("UPDATE ticker SET is_recent=0 WHERE exchange=:exchange AND currency1=:currency1 AND currency2=:currency2");
	$q->execute(array(
		"exchange" => $exchange,
		"currency1" => $currency1,
		"currency2" => $currency2,
	));

	// all other data from today is now old
	// NOTE if the system time changes between the next two commands, then we may erraneously
	// specify that there is no valid daily data. one solution is to specify NOW() as $created_at rather than
	// relying on MySQL
	$q = db()->prepare("UPDATE ticker SET is_daily_data=0 WHERE is_daily_data=1 AND exchange=:exchange AND currency1=:currency1 AND currency2=:currency2 AND
		date_format(created_at, '%d-%m-%Y') = date_format(now(), '%d-%m-%Y')");
	$q->execute(array(
		"exchange" => $exchange,
		"currency1" => $currency1,
		"currency2" => $currency2,
	));

	// insert in new ticker value
	$q = db()->prepare("INSERT INTO ticker SET is_recent=1, exchange=:exchange, currency1=:currency1, currency2=:currency2, last_trade=:last_trade, job_id=:job_id, is_daily_data=1");
	$q->execute(array(
		"exchange" => $exchange,
		"currency1" => $currency1,
		"currency2" => $currency2,
		"last_trade" => $last_trade,
		// don't have buy, sell, or volume
		"job_id" => $job_id,
	));

	crypto_log("Inserted new ticker id=" . db()->lastInsertId());
}
