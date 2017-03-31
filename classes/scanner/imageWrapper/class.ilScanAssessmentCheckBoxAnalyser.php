<?php

/**
 * Class ilScanAssessmentCheckBoxAnalyser
 */
class ilScanAssessmentCheckBoxAnalyser
{
    private $image;
	private $pixels;
	private $bounding_box;
	private $threshold;
	private $coverage;

	public function rightmost()
	{
		$x = PHP_INT_MIN;
		$y = 0;

		foreach ($this->coordinates() as $pixel) {
			if ($pixel[0] > $x) {
				list($x, $y) = $pixel;
			}
		}

		return array($x, $y);
	}

	public function __construct($image, $x, $y, $threshold)
	{
	    $this->threshold = $threshold;
	    $this->coverage = 0.75;

		$pixels = array();
		self::gatherPixels($image, $x, $y, $threshold, $pixels);
		$this->pixels = $pixels;

		$this->bounding_box = $this->calculateBoundingBox();

		$this->image = $image;
	}
	
	private static function grey($image, $x, $y) {
		$color = imagecolorat($image, $x, $y);

		$blue	= 0x0000ff & $color;
		$green	= 0x00ff00 & $color;
		$green	= $green >> 8;
		$red	= 0xff0000 & $color;
		$red	= $red >> 16;

		return ($red + $green + $blue) / 3;
	}

	private static function gatherPixels($image, $x, $y, $threshold, &$pixels)
	{
		// essentially a flood fill that detects all black marker pixels.

		$stack = array(array($x, $y));

		array_push($stack, array($x - 1, $y - 1));
		array_push($stack, array($x - 1, $y + 1));
		array_push($stack, array($x + 1, $y - 1));
		array_push($stack, array($x + 1, $y + 1));

		$w = imagesx($image);
		$h = imagesy($image);

		while (count($stack) > 0)
		{
			list($x, $y) = array_pop($stack);

			if ($x < 0 || $y < 0 || $x >= $w || $y >= $h)
			{
				continue;
			}

			$coordinates = $x . '/' . $y;

			if (isset($pixels[$coordinates]))
			{
				continue;
			}

			if (self::grey($image, $x, $y) < $threshold) // black?
			{
				$pixels[$coordinates] = true;
				array_push($stack, array($x + 1, $y));
				array_push($stack, array($x - 1, $y));
				array_push($stack, array($x, $y + 1));
				array_push($stack, array($x, $y - 1));
			}
		}
	}

	private function coordinates() 
	{
		$coordinates = array();
		foreach (array_keys($this->pixels) as $xy) {
			list($x, $y) = explode('/', $xy);
			array_push($coordinates, array(intval($x), intval($y)));
		}
		return $coordinates;
	}

	private function calculateBoundingBox() {
		$x = array();
		$y = array();

		foreach ($this->coordinates() as $pixel) {
			array_push($x, $pixel[0]);
			array_push($y, $pixel[1]);
		}

		if (count($x) > 0 && count($y) > 0) {
			return array(min($x), min($y), max($x), max($y));
		} else {
		    return false;
        }
	}

	private function testLine($pixel, $k0, $k1) {
        $threshold = $this->threshold;
        $coverage = $this->coverage;

        $total = (float)($k1 - $k0 + 1);

        $n = 0;
        for ($k = $k0; $k <= $k1; $k++) {
            if ($pixel($k) < $threshold) {
                $n++;
            }

            // early exit: even if all remaining pixels turn
            // out to be good, can the coverage be reached?
            $r = $k1 - $k;
            if (($n + $r) / $total < $coverage) {
               return false;
            }
        }

        return $n / $total >= $coverage;
    }

    private function testHorizontalLine($x0, $x1, $y) {
        $image = $this->image;
	    return $this->testLine(function($k) use ($image, $y) {
	        return self::grey($image, $k, $y);
        }, $x0, $x1);
    }

    private function testVerticalLine($x, $y0, $y1) {
	    $image = $this->image;
        return $this->testLine(function($k) use ($image, $x) {
            return self::grey($image, $x, $k);
        }, $y0, $y1);
    }

    private function testRectangleError($x0, $y0, $x1, $y1) {
        if (!$this->testHorizontalLine($x0, $x1, $y0)) {
            return 'top';
        }
        if (!$this->testHorizontalLine($x0, $x1, $y1)) {
            return 'bottom';
        }
        if (!$this->testVerticalLine($x0, $y0, $y1)) {
            return 'left';
        }
        if (!$this->testVerticalLine($x1, $y0, $y1)) {
            return 'right';
        }
        return false;
    }

    private function clipLeft($x0, $y0, $x1, $y1) {
        while (true) {
            $x0 += 1;

            if ($x0 >= $x1) {
                return false;
            }

            if ($this->testVerticalLine($x0, $y0, $y1)) {
                return $x0;
            }
        }
    }

    private function clipRight($x0, $y0, $x1, $y1) {
        while (true) {
            $x1 -= 1;

            if ($x0 >= $x1) {
                return false;
            }

            if ($this->testVerticalLine($x1, $y0, $y1)) {
                return $x1;
            }
        }
    }

    private function clipTop($x0, $y0, $x1, $y1) {
        while (true) {
            $y0 += 1;

            if ($y0 >= $y1) {
                return false;
            }

            if ($this->testHorizontalLine($x0, $x1, $y0)) {
                return $y0;
            }
        }
    }

    private function clipBottom($x0, $y0, $x1, $y1) {
        while (true) {
            $y1 -= 1;

            if ($y0 >= $y1) {
                return false;
            }

            if ($this->testHorizontalLine($x0, $x1, $y1)) {
                return $y1;
            }
        }
    }

    public function detectRectangle() {
	    $nodes = array();

	    if ($this->bounding_box) {
	        array_push($nodes, $this->bounding_box);
        }

	    while (!empty($nodes)) {
            list($x0, $y0, $x1, $y1) = array_pop($nodes);

            $fail = $this->testRectangleError($x0, $y0, $x1, $y1);

            if ($fail === false) {
                return array($x0, $y0, $x1, $y1);
            }

            // note that we add the nodes in inverse order of intended traversal, as
            // they are fetched via array_pop() for reasons of efficiency.

            switch ($fail) {
                case 'left':
                case 'right':
                    $y0_clipped = $this->clipTop($x0, $y0, $x1, $y1);
                    $y1_clipped = $this->clipBottom($x0, $y0, $x1, $y1);
                    if ($y0_clipped !== false && $y1_clipped !== false) {
                        array_push($nodes, array($x0, $y0_clipped, $x1, $y1_clipped));
                    }
                    if ($y1_clipped !== false) {
                        array_push($nodes, array($x0, $y0, $x1, $y1_clipped));
                    }
                    if ($y0_clipped !== false) {
                        array_push($nodes, array($x0, $y0_clipped, $x1, $y1));
                    }
                    break;

                case 'top':
                case 'bottom':
                    $x0_clipped = $this->clipLeft($x0, $y0, $x1, $y1);
                    $x1_clipped = $this->clipRight($x0, $y0, $x1, $y1);
                    if ($x0_clipped !== false && $x1_clipped !== false) {
                        array_push($nodes, array($x0_clipped, $y0, $x1_clipped, $y1));
                    }
                    if ($x1_clipped !== false) {
                        array_push($nodes, array($x0, $y0, $x1_clipped, $y1));
                    }
                    if ($x0_clipped !== false) {
                        array_push($nodes, array($x0_clipped, $y0, $x1, $y1));
                    }
                    break;

                default:
                    throw new \Exception('illegal box fail code '. $fail);
            }

            switch ($fail) {
                case 'left':
                    $x0_clipped = $this->clipLeft($x0, $y0, $x1, $y1);
                    if ($x0_clipped !== false) {
                        array_push($nodes, array($x0_clipped, $y0, $x1, $y1));
                    }
                    break;

                case 'right':
                    $x1_clipped = $this->clipRight($x0, $y0, $x1, $y1);
                    if ($x1_clipped !== false) {
                        array_push($nodes, array($x0, $y0, $x1_clipped, $y1));
                    }
                    break;

                case 'top':
                    $y0_clipped = $this->clipTop($x0, $y0, $x1, $y1);
                    if ($y0_clipped !== false) {
                        array_push($nodes, array($x0, $y0_clipped, $x1, $y1));
                    }
                    break;

                case 'bottom':
                    $y1_clipped = $this->clipBottom($x0, $y0, $x1, $y1);
                    if ($y1_clipped !== false) {
                        array_push($nodes, array($x0, $y0, $x1, $y1_clipped));
                    }
                    break;

                default:
                    throw new \Exception('illegal box fail code '. $fail);
            }
        }

        return false;
    }
}
