<?php

ilScanAssessmentPlugin::getInstance()->includeClass('scanner/class.ilScanAssessmentScanner.php');
ilScanAssessmentPlugin::getInstance()->includeClass('class.ilScanAssessmentGlobalSettings.php');
ilScanAssessmentPlugin::getInstance()->includeClass('scanner/imageWrapper/class.ilScanAssessmentRegion.php');

/**
 * Class ilScanAssessmentMarkerDetection
 * @author Guido Vollbach <gvollbach@databay.de>
 */
class ilScanAssessmentMarkerDetection extends ilScanAssessmentScanner
{
	const MARKER_START_SHOULD_BE_AWAY_FROM_IMAGE_START = 5;

	protected $top_left_length;

	protected $marker_names = array('top' => 'Top Left', 'bottom' => 'Bottom Left', 'right' => 'Top Right');

	/**
	 * ilScanAssessmentMarkerDetection constructor.
	 * @param $fn
	 */
	public function __construct($fn)
	{
		parent::__construct($fn);
	}

	/**
	 * @param $path
	 * @return array|bool
	 */
	public function getMarkerPosition($path)
	{
		$im = $this->getImage();
		$this->log->debug(sprintf('Starting marker detection...'));
		$this->setThreshold(self::LOWER_THRESHOLD);
		$marker = $this->findMarker($im, false, $this->getThreshold(), $path);
		if(!$marker)
		{
			$this->log->warn(sprintf('Could not detect Marker!'));
		}
		$this->log->debug(sprintf('Marker detection done.'));
		return $marker;
	}

	/**
	 * @param $where
	 * @param $threshold
	 * @return bool|ilScanAssessmentVector
	 */
	private function locateMarker($where, $threshold)
	{
		$scan = $this->probeMarkerPosition($where, $threshold);
		if($scan === false)
		{
			$this->log->debug(sprintf('Probing found nothing.'));
			return false;
		}

		$name = $this->marker_names[$where];

		$this->log->debug(sprintf('%s Marker found Start[%s, %s], End[%s, %s] starting to detect exact position.',
			$name,
			$scan->getStart()->getX(), $scan->getStart()->getY(),
			$scan->getEnd()->getX(), $scan->getEnd()->getY()));

		$locate = $this->detectExactMarkerPosition($scan, $threshold);
		if($locate !== null)
		{
			$this->log->debug(sprintf('Exact %s Marker found at [%s, %s] with length %s.',
				$name, $locate->getPosition()->getX(), $locate->getPosition()->getY(), $locate->getLength()));

			return $locate;
		}
		return false;
	}

	/**
	 * @param $im
	 * @param $rotated
	 * @param $path
	 */
	private function rotate(&$im, $rotated, $path)
	{
		if(!$rotated)
		{
			$this->log->debug(sprintf('Trying to rotate...'));
			$im = $this->image_helper->rotate(180);
			$this->log->debug(sprintf('Rotation done, rescanning...'));
			$this->image_helper->setImage($im);
			$this->drawTempImage($im, $path . '/new_file' . ilScanAssessmentGlobalSettings::getInstance()->getInternFileType());
		}
	}

	/**
	 * @param                      $im
	 * @param bool                 $rotated
	 * @param int                  $threshold
	 * @var ilScanAssessmentVector $locate_top_left
	 * @var ilScanAssessmentVector $locate_bottom_left
	 * @return array|bool
	 */
	public function findMarker($im, $rotated = false, $threshold = 150, $path)
	{

		$locate_top_left    = null;
		$locate_bottom_left = null;

		$locate_top_left = $this->locateMarker('top', $threshold);
		if($locate_top_left === false)
		{
			$this->rotate($im, $rotated, $path);
			$locate_top_left = $this->locateMarker('top', $threshold);
		}

		if($locate_top_left === false)
		{
			return false;
		}

		$locate_bottom_left = $this->locateMarker('bottom', $threshold);

		if($locate_bottom_left === false)
		{
			return false;
		}

		$dx = $locate_bottom_left->getPosition()->getX() - $locate_top_left->getPosition()->getX();
		$dy = $locate_bottom_left->getPosition()->getY() - $locate_top_left->getPosition()->getY();
		$this->log->debug(sprintf('dX,dY [%s, %s] => atan %s.', $dx, $dy, atan2($dx, $dy)));

		$rad = rad2deg(atan2($dx, $dy));
		$this->log->debug(sprintf('Rotation (%s).', $rad));
		if($rotated == false && abs($rad) > 0.05)
		{
			$this->log->debug(sprintf('Image seems to be rotated (%s).', $rad));
			$im = $this->image_helper->rotate($rad * -1);
			$this->image_helper->setImage($im);
			$this->setImage($im);
			$this->image_helper->drawTempImage($im, $path . '/rotate_file' . ilScanAssessmentGlobalSettings::getInstance()->getInternFileType());
			return $this->findMarker($im, true, $threshold, $path);
		}

		$this->drawDebugSquareFromVector($locate_top_left);
		$this->drawDebugSquareFromVector($locate_bottom_left);
		return array($locate_top_left, $locate_bottom_left);

	}

	/**
	 * @param ilScanAssessmentPoint $near
	 * @return ilScanAssessmentPoint
	 */
	protected function findTopLeftMarker(ilScanAssessmentPoint $near)
	{
		$first = new ilScanAssessmentPoint($near->getX() - 200, $near->getY() - 100);
		$last  = new ilScanAssessmentPoint($near->getX() + 100, $near->getY() + 200);
		$point = new ilScanAssessmentPoint($first->getX(), $first->getY());
		for($y = $first->getY(); $y != $last->getY(); $y++)
		{
			for($x = $first->getX(); $x != $last->getX(); $x++)
			{
				$gray = $this->image_helper->getGrey(new ilScanAssessmentPoint($x, $y));
				if($gray < 150)
				{
					if ($this->image_helper->getGrey(new ilScanAssessmentPoint($x, $y )) > 150)
					{
						$point->setX($x);
					}

					if ($this->image_helper->getGrey(new ilScanAssessmentPoint($x, $y + 15)) < 150)
					{
						$point->setY($y);
					}

				}
			}
		}
		$this->image_helper->drawSquareFromTwoPoints($this->temp_image, $first,  $last, $this->image_helper->getBlue());
		$this->image_helper->drawSquareFromVector($this->temp_image, new ilScanAssessmentVector($point, 5), $this->image_helper->getGreen());
		$this->image_helper->drawTempImage($this->temp_image, 'detection' . ilScanAssessmentGlobalSettings::getInstance()->getInternFileType());
		return $point;
	}

	/**
	 * @param ilScanAssessmentLine $find
	 * @param                      $threshold
	 * @return ilScanAssessmentVector
	 */
	public function detectExactMarkerPosition($find, $threshold)
	{
		$sdx = $find->getEnd()->getX() - $find->getStart()->getX();
		$sdy = $find->getEnd()->getY() - $find->getStart()->getY();

		$mx = $find->getStart()->getX() + $sdx / 2;
		$my = $find->getStart()->getY() + $sdy / 2;

		$dx = abs($sdx);
        $dy = abs($sdy);

        $this->log->debug(sprintf('Estimated marker position [%s, %s], size: [%s, %s]', $mx, $my, $dx, $dy));

        $d = 1.0 / max($dx, $dy);
        $min_size = 0.1 * $dx * $dy;

		for($i = 0; $i < 2; $i += $d)
		{
		    foreach(array(-1, 1) as $s)
            {
                $p = new ilScanAssessmentPoint(intval($mx + $s * $dx * $i), intval($my + $s * $dy * $i));
                $gray = $this->image_helper->getGrey($p);
                if($gray < $threshold)
                {
                    $region = new ilScanAssessmentRegion($this->image_helper, $p, $threshold);

                    // make sure we got the actual marker and not some random dirt by checking
                    // the overall size is reasonable.

                    if($region->size() > $min_size)
                    {
                        $len2 = sqrt($dx * $dx + $dy * $dy);
                        return new ilScanAssessmentVector($region->centre(), $len2);
                    }
                    else
                    {
                        $this->log->debug(sprintf('Region at [%s, %s] smaller than %s, details: %s',
                            $p->getX(), $p->getY(), $min_size, $region->dump()));
                    }
                }
            }
		}
	}

	/**
	 * @param string              $top_bottom
	 * @param int                 $threshold
	 * @var ilScanAssessmentPoint $found_start
	 * @var ilScanAssessmentPoint $found_end
	 * @return bool|ilScanAssessmentLine
	 */
	public function probeMarkerPosition($top_bottom = 'top', $threshold = 150)
	{
		$width     = $this->image_helper->getImageSizeX();
		$step_size = 3;
		$found     = false;
		$subDX     = 0;
		$beginD    = -1;
		/** @var ilScanAssessmentPoint $found_start */
		$found_start = null;
		/** @var ilScanAssessmentPoint $found_end */
		$found_end = null;
		$max_width = $width / 4 * 3;

		for($d = 55; $d < $max_width; $d += $step_size)
		{
			$len = $this->getLengthFromScanLine($d, $top_bottom, $threshold);

			if(($beginD == -1 && $len < $d) ||
				($beginD != -1 && $len - $subDX / 2 <= $beginD)
			)
			{
				if($found == false)
				{
					$found       = true;
					$subDX       = 0;
					$beginD      = $len;
					$found_start = new ilScanAssessmentPoint($len, $d - $len);
					$found_end   = new ilScanAssessmentPoint($len, $d - $len);
				}
				else
				{
					$subDX     += $step_size;
					$found_end = new ilScanAssessmentPoint($len, $d - $len);
				}
				if($top_bottom == 'top')
				{
					$this->drawDebugLine(new ilScanAssessmentLine(new ilScanAssessmentPoint(0 + $subDX / 2, $d - $subDX / 2), new ilScanAssessmentPoint($len, $d - $len)), 0xffff00);
				}
				else
				{
					$this->drawDebugLine(new ilScanAssessmentLine(new ilScanAssessmentPoint(0 + $subDX / 2, $this->image_helper->getImageSizeY() - ($d - $subDX / 2)), new ilScanAssessmentPoint($len, $this->image_helper->getImageSizeY() - ($d - $len))), 0xff0000);
				}
			}
			else
			{
				if($found == true  && $found_start->getX() > self::MARKER_START_SHOULD_BE_AWAY_FROM_IMAGE_START)
				{
					$found_line = new ilScanAssessmentLine($found_start, $found_end);
					$length     = sqrt(
						($found_start->getX() - $found_end->getX()) *
						($found_start->getX() - $found_end->getX()) +
						($found_start->getY() - $found_end->getY()) *
						($found_start->getY() - $found_end->getY())
					);

					if($length > 0)
					{
						if(($width / $length) > 35 && ($width / $length) < 100)
						{
							$this->drawDebugLine($found_line, $this->image_helper->getYellow());

							if($top_bottom == "bottom")
							{
								$found_line->getStart()->setY($this->image_helper->getImageSizeY() - $found_line->getStart()->getY());
								$found_line->getEnd()->setY($this->image_helper->getImageSizeY() - $found_line->getEnd()->getY());
							}
							return $found_line;
							break;
						}
					}
				}
				$found = false;
				$beginD    = -1;
			}
		}
		return false;
	}

	/**
	 * @param        $d
	 * @param string $top_bottom
	 * @param        $threshold
	 * @return int
	 */
	public function getLengthFromScanLine($d, $top_bottom = 'top', $threshold)
	{
		for($x = 0; $x < $d; $x++)
		{
			if($top_bottom == 'top')
			{
				$y = $d - $x;
			}
			else
			{
				$y = $this->image_helper->getImageSizeY() - $d + $x;
			}

			$gray = $this->image_helper->getGrey(new ilScanAssessmentPoint($x, $y));

			if($gray < $threshold)
			{

				$len  = 15;
				$mean = $gray;
				for($i = 1; $i < $len; $i += 3)
				{
					if($top_bottom == 'top')
					{
						$y2 = $d - ($x + $i);
					}
					else
					{
						$y2 = $this->image_helper->getImageSizeY() - $d + ($x + $i);
					}
					$mean += $this->image_helper->getGrey(new ilScanAssessmentPoint($x, $y2));
				}
				if($mean / $len < $threshold)
				{
					return $x;
				}
			}
			$this->drawDebugPixel(new ilScanAssessmentPoint($x, $y), 0x000fff);
		}
		return $x;
	}
}