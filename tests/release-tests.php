<?php

require_once(__DIR__ . "/../inc/simpletest/autorun.php");

require_once(__DIR__ . "/../inc/global.php");

/**
 * Tests related to the release quality of Openclerk - i.e. more like integration tests.
 */
class ReleaseTestsTest extends UnitTestCase {

	/**
	 * Check that each require(), require_once(), include() or include_once() within Openclerk
	 * uses __DIR__ rather than a path that assumes a relative dir.
	 * For example, if a file says require("inc/global.php"), this assumes _this_ file is always
	 * the root relative.
	 */
	function testRequireUsesDir() {
		$files = $this->recurseFindFiles("..", "");
		$this->assertTrue(count($files) > 0);

		foreach ($files as $f) {
			$s = file_get_contents($f);
			if (preg_match('#\n(require|require_once|include|include_once)\(("|\')#m', $s, $matches)) {
				throw new Exception("Found require() that did not use __DIR__ in '" . $f . "': '" . $matches[0] . "'");
			}
		}

		$this->assertTrue(array_equals(array(1, 2), array(2, 1)));
		$this->assertFalse(array_equals(array(1, 2), array(1, 2, 3)));
	}

	function recurseFindFiles($dir, $name) {
		$result = array();
		if ($handle = opendir($dir)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					if (substr(strtolower($entry), -4) == ".php") {
						$result[] = $dir . "/" . $entry;
					} else if (is_dir($dir . "/" . $entry)) {
						if ($name == 'inc') {
							// ignore subdirs of inc
							continue;
						}
						$result = array_merge($result, $this->recurseFindFiles($dir . "/" . $entry, $entry));
					}
				}
			}
			closedir($handle);
		}
		return $result;
	}

	/**
	 * Test that all require()s reference a valid file, to prevent a problem like r426
	 */
	function testAllIncludesExist() {
		$files = $this->recurseFindFiles("..", "");
		$this->assertTrue(count($files) > 0);

		foreach ($files as $f) {
			$s = file_get_contents($f);
			if (preg_match_all('#\n(require|require_once|include|include_once)\(__DIR__ . ("|\')([^"\']+)("|\')#m', $s, $matches_array, PREG_SET_ORDER)) {
				foreach ($matches_array as $matches) {
					$path = $matches[3];

					// path should start with /
					$this->assertTrue(substr($path, 0, 1) == "/", "Included path '$path' in '$f' did not start with /");

					// get relative dir
					$bits = explode("/", $f);
					unset($bits[count($bits)-1]);	// remove filename
					unset($bits[0]);	// remove ../
					$resolved = __DIR__ . "/../" . implode("/", $bits) . $path;
					$this->assertTrue(file_exists($resolved), "Included path '$path' in '$f' was not found: [$resolved]");
				}
			}
		}

	}

	/**
	 * Lint all PHP files, to prevent typos from causing release problems
	 */
	function testLintAll() {
		$files = $this->recurseFindFiles("..", "");
		$this->assertTrue(count($files) > 0);

		foreach ($files as $f) {
			$return = 0;
			$output_array = array();
			$output = exec("php -l \"" . $f . "\"", $output_array, $return);
			$this->assertFalse($return, "File '$f' failed lint: '$output' ($return)");
			if ($return) {
				foreach ($output_array as $line) {
					echo "<br>" . $line . "\n";
				}
			}
		}

	}


}