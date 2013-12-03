<?php

/**
 * This page displays historical data publically.
 */

require(__DIR__ . "/inc/global.php");
require(__DIR__ . "/layout/graphs.php");

require(__DIR__ . "/layout/templates.php");

$messages = array();
$errors = array();

$historical_graphs = graph_types_public();

$permitted_days = get_permitted_days();
$days = isset($permitted_days[require_get('days', false)]) ? $permitted_days[require_get('days')]['days'] : 45;
$user = user_logged_in() ? get_user(user_id()) : false;

$id = require_get("id", false);
if ($id && isset($historical_graphs[$id])) {
	// we're displaying a specific graph

	$name = require_get('name', false);
	$title = $name;
	// if we've got a name, then we want to get the title too
	if (isset($historical_graphs[$id]['title_callback'])) {
		$callback = $historical_graphs[$id]['title_callback'];
		$title = $callback($id, $title);
	}
	page_header("Historical Data: " . $historical_graphs[$id]["heading"] . ($title ? ": " . $title : ""), "page_historical", array('common_js' => true, 'jquery' => true, 'jsapi' => true));

	$graph = array(
		'graph_type' => $id,
		'width' => 8,
		'height' => 4,
		'page_order' => 0,
		'days' => $days,
		'id' => 0,
		'arg0_resolved' => $name,
		'public' => true,
	);

	$extra_args = $name ? array("name" => $name) : array();

	?>
	<?php if (!($user && $user['is_premium'])) { ?>
	<div class="tip tip_float">
		With a <a href="<?php echo htmlspecialchars(url_for('premium')); ?>">premium account</a>, you can apply technical
		indicators to historical exchange and security data, such as Moving Averages (SMA), Bollinger Bands (BOLL), and Relative Strength Index (RSI).
	</div>
	<?php } ?>

	<h1>Historical Data: <?php echo htmlspecialchars($historical_graphs[$id]["heading"]) . ($title ? ": " . htmlspecialchars($title) : ""); ?></h1>

	<p class="backlink">
	<a href="<?php echo htmlspecialchars(url_for('historical')); ?>">&lt; Back to Historical Data</a>
	<?php foreach ($permitted_days as $key => $days) { ?>
	| <a href="<?php echo htmlspecialchars(url_for('historical', $extra_args + array('id' => $id, 'days' => $key))); ?>"><?php echo htmlspecialchars($days['title']); ?></a>
	<?php } ?>
	</p>

	<div class="graph_collection">
	<div class="graph graph_<?php echo htmlspecialchars($graph['graph_type']); ?>" id="graph<?php echo htmlspecialchars($graph['id']); ?>">
		<?php render_graph($graph, true /* is public */); ?>
	</div>
	</div>

	<?php

} else {

	// we want to display a list of all possible graphs

	page_header("Historical Data", "page_historical");

	?>

	<h1>Historical Data</h1>

	<div class="columns2">
	<div class="column">

	<?php
	$last_exchange = null;
	foreach ($historical_graphs as $graph_key => $def) {
		if (!isset($def['exchange']) || (isset($def['admin']) && $def['admin']))
			continue;

		if ($def['exchange'] != $last_exchange) {
			if ($last_exchange != null) echo "</ul>\n";
			echo "<h2>" . htmlspecialchars(get_exchange_name($def['exchange'])) . "</h2>\n";
			echo "<ul class=\"historical_graphs\">\n";
			$last_exchange = $def['exchange'];
		}

		if (isset($def['category'])) {
			continue;
		}

		if (!isset($def['arg0'])) {
			echo "<li><a href=\"" . htmlspecialchars(url_for('historical', array('id' => $graph_key, 'days' => 180))) . "\">" . htmlspecialchars($def['title']) . "</a>";
			if (in_array(str_replace("_daily", "", $graph_key), get_new_exchange_pairs())) {
				echo " <span class=\"new\">new</span>";
			}
			echo "</li>\n";
		}
	}
	if ($last_exchange != null) echo "</ul>\n";
	?>
	</ul>

	</div>
	<div class="column">

	<?php foreach ($historical_graphs as $graph_key => $def) {
		$bits = explode("_", $graph_key);
		if ($bits[0] == "securities") {
			$security_type = $bits[1];
			$exchanges = get_security_exchange_pairs();
			$tables = get_security_exchange_tables();
			if (!isset($exchanges[$security_type])) {
				throw new Exception("Unknown security type '" . htmlspecialchars($security_type) . "'");
			}

			// get all "new" securities
			$q = db()->prepare("SELECT * FROM " . $tables[$security_type] . " WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
			$q->execute();
			$new_securities = array();
			while ($sec = $q->fetch()) {
				$new_securities[$sec['name']] = 1;
			}
		}

		// TODO should refactor this with layout/graphs.php
		if (isset($def['arg0'])) {
			$values = $def['arg0'](isset($def['param0']) ? $def['param0'] : false, isset($def['param1']) ? $def['param1'] : false);
			if ($values) {
				echo "<h2>" . htmlspecialchars($def['heading']);
				if ($bits[0] == "securities" && in_array($security_type, get_new_security_exchanges())) {
					echo " <span class=\"new\">new</span>";
				}
				echo "</h2>\n<ul class=\"historical_graphs\">";
				if ($graph_key == "external_historical") {
					echo "<li><a href=\"" . htmlspecialchars(url_for('external')) . "\">External API status</a></li>";
				} else {
					foreach ($values as $key => $security) {
						$title = $security;
						if (isset($def['title_callback'])) {
							$callback = $def['title_callback'];
							$title = $callback($graph_key, $security);
						}
						echo "<li><a href=\"" . htmlspecialchars(url_for('historical', array('id' => $graph_key, 'days' => 180, 'name' => $security))) . "\">" . htmlspecialchars($title) . "</a>";
						// is this new?
						if ($bits[0] == "securities" && isset($new_securities[$security])) {
							echo " <span class=\"new\">new</span>";
						}
						echo "</li>\n";
					}
				}
				echo "</ul>\n";
			}
		}
	} ?>

	</div>
	</div>

	<?php

}

page_footer();
