<?php

/**
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * For the full copyright and license information, please view the LICENSE
 *
 * @package 2Moons
 * @author Jan-Otto Kröpke <slaver7@gmail.com>
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 */


class ShowStatisticsPage extends AbstractGamePage
{
    public static $requireModule = MODULE_STATISTICS;

	function __construct() 
	{
		parent::__construct();
	}

    function show()
    {
        global $USER, $LNG;

        $who   	= HTTP::_GP('who', 1);
        $type  	= HTTP::_GP('type', 1);
        $range 	= HTTP::_GP('range', 1);

        switch ($type)
        {
            case 2:
                $Order   = "fleet_rank";
                $Points  = "fleet_points";
                $Rank    = "fleet_rank";
            break;
            case 3:
                $Order   = "tech_rank";
                $Points  = "tech_points";
                $Rank    = "tech_rank";
            break;
            case 4:
                $Order   = "build_rank";
                $Points  = "build_points";
                $Rank    = "build_rank";
            break;
            case 5:
                $Order   = "defs_rank";
                $Points  = "defs_points";
                $Rank    = "defs_rank";
            break;
            default:
                $Order   = "total_rank";
                $Points  = "total_points";
                $Rank    = "total_rank";
            break;
        }

        $RangeList  = array();
        $query      = array();

		$db 	= Database::get();
		$config	= Config::get();

        switch($who)
        {
            case 1:
                $MaxUsers 	= $config->users_amount;
                $range		= min($range, $MaxUsers);
                $LastPage 	= max(1, ceil($MaxUsers / 100));

                for ($Page = 0; $Page < $LastPage; $Page++)
                {
                    $PageValue      				= ($Page * 100) + 1;
                    $PageRange      				= $PageValue + 99;
                    $Selector['range'][$PageValue] 	= $PageValue."-".$PageRange;
                }

                $start = max(floor(($range - 1) / 100) * 100, 0);

				if ($config->stat == 2) {
					$sql = "SELECT DISTINCT s.*, u.id, u.username, u.ally_id, a.ally_name FROM %%STATPOINTS%% as s
					INNER JOIN %%USERS%% as u ON u.id = s.id_owner
					LEFT JOIN %%ALLIANCE%% as a ON a.id = s.id_ally
					WHERE s.universe = :universe AND s.stat_type = 1 AND u.authlevel < :authLevel
					ORDER BY ".$Order." ASC LIMIT :offset, :limit;";
					$query = $db->select($sql, array(
						':universe'	=> Universe::current(),
						':authLevel'=> $config->stat_level,
						':offset'	=> $start,
						':limit'	=> 100,
					));
				} else {
					$sql = "SELECT DISTINCT s.*, u.id, u.username, u.ally_id, a.ally_name FROM %%STATPOINTS%% as s
					INNER JOIN %%USERS%% as u ON u.id = s.id_owner
					LEFT JOIN %%ALLIANCE%% as a ON a.id = s.id_ally
					WHERE s.universe = :universe AND s.stat_type = 1
					ORDER BY ".$Order." ASC LIMIT :offset, :limit;";
					$query = $db->select($sql, array(
						':universe'	=> Universe::current(),
						':offset'	=> $start,
						':limit'	=> 100,
					));
				}

				$RangeList	= array();

                foreach ($query as $StatRow)
                {
                    $RangeList[]	= array(
                        'id'		=> $StatRow['id'],
                        'name'		=> $StatRow['username'],
                        'points'	=> pretty_number($StatRow[$Points]),
                        'allyid'	=> $StatRow['ally_id'],
                        'rank'		=> $StatRow[$Rank],
                        'allyname'	=> $StatRow['ally_name'],
                    );
                }

            break;
            case 2:
                $sql = "SELECT COUNT(*) as state FROM %%ALLIANCE%% WHERE `ally_universe` = :universe;";
				$MaxAllys = $db->selectSingle($sql, array(
					':universe'	=> Universe::current(),
				), 'state');

				$range		= min($range, $MaxAllys);
                $LastPage 	= max(1, ceil($MaxAllys / 100));
				
                for ($Page = 0; $Page < $LastPage; $Page++)
                {
                    $PageValue      				= ($Page * 100) + 1;
                    $PageRange      				= $PageValue + 99;
                    $Selector['range'][$PageValue] 	= $PageValue."-".$PageRange;
                }

                $start = max(floor(($range - 1) / 100) * 100, 0);

                $sql = 'SELECT DISTINCT s.*, a.id, a.ally_members, a.ally_name FROM %%STATPOINTS%% as s
                INNER JOIN %%ALLIANCE%% as a ON a.id = s.id_owner
                WHERE universe = :universe AND stat_type = 2
                ORDER BY '.$Order.' ASC LIMIT :offset, :limit;';
				$query = $db->select($sql, array(
					':universe'	=> Universe::current(),
					':offset'	=> $start,
					':limit'	=> 100,
				));

				foreach ($query as $StatRow)
                {
                    $RangeList[]	= array(
                        'id'		=> $StatRow['id'],
                        'name'		=> $StatRow['ally_name'],
                        'members'	=> $StatRow['ally_members'],
                        'rank'		=> $StatRow[$Rank],
                        'mppoints'	=> pretty_number(floor($StatRow[$Points] / $StatRow['ally_members'])),
                        'points'	=> pretty_number($StatRow[$Points]),
                    );
                }

            break;
        }

        $history = $this->getStatHistory($who, $query, $Rank, $Points);
        foreach ($RangeList as $key => $entry)
        {
            $RangeList[$key]['history'] = $history[$entry['id']];
        }

        $Selector['who'] 	= array(1 => $LNG['st_player'], 2 => $LNG['st_alliance']);
        $Selector['type']	= array(1 => $LNG['st_points'], 2 => $LNG['st_fleets'], 3 => $LNG['st_researh'], 4 => $LNG['st_buildings'], 5 => $LNG['st_defenses']);


		require_once 'includes/classes/Cronjob.class.php';

        $this->assign(array(
            'Selectors'				=> $Selector,
            'who'					=> $who,
            'type'					=> $type,
            'range'					=> floor(($range - 1) / 100) * 100 + 1,
            'RangeList'				=> $RangeList,
            'CUser_ally'			=> $USER['ally_id'],
            'CUser_id'				=> $USER['id'],
            'stat_date'				=> _date($LNG['php_tdformat'], Cronjob::getLastExecutionTime('statistic'), $USER['timezone']),
        ));

        $this->display('page.statistics.default.tpl');
    }

    // Change of rank and points since the daily snapshots (class.statbuilder.php):
    // a day ago means since yesterday's snapshot, taken right after midnight.
    private function getStatHistory($statType, array $rows, $rankField, $pointsField)
    {
        global $LNG;

        $periods = array(1 => 'st_history_day', 7 => 'st_history_week', 14 => 'st_history_two_weeks', 30 => 'st_history_month');

        $today = new DateTime();
        $today->setTimestamp(TIMESTAMP);
        $dates = array();
        foreach ($periods as $days => $label)
        {
            $date           = clone $today;
            $dates[$days]   = $date->modify('-'.$days.' days')->format('Y-m-d');
        }

        $snapshots = array();
        $ids = array();
        foreach ($rows as $row)
        {
            $ids[] = (int) $row['id'];
        }

        if (!empty($ids))
        {
            $sql = "SELECT id_owner, stat_date, ".$rankField." AS old_rank, ".$pointsField." AS old_points
            FROM %%STAT_HISTORY%%
            WHERE stat_type = :type AND id_owner IN (".implode(',', $ids).") AND stat_date IN ('".implode("','", $dates)."');";
            foreach (Database::get()->select($sql, array(':type' => $statType)) as $snapshot)
            {
                $snapshots[$snapshot['id_owner']][$snapshot['stat_date']] = $snapshot;
            }
        }

        $history = array();
        foreach ($rows as $row)
        {
            $lines = array();
            foreach ($periods as $days => $label)
            {
                $line = array('label' => $LNG[$label]);
                if (!isset($snapshots[$row['id']][$dates[$days]]))
                {
                    $line['rank']   = $this->formatHistoryChange(null);
                    $line['points'] = $this->formatHistoryChange(null);
                }
                else
                {
                    $snapshot       = $snapshots[$row['id']][$dates[$days]];
                    $line['rank']   = $this->formatHistoryChange($snapshot['old_rank'] - $row[$rankField], true);
                    $line['points'] = $this->formatHistoryChange($row[$pointsField] - $snapshot['old_points']);
                }
                $lines[] = $line;
            }
            $history[$row['id']] = $lines;
        }

        return $history;
    }

    private function formatHistoryChange($change, $isRank = false)
    {
        global $LNG;

        if ($change === null)
        {
            return array('color' => '#888888', 'text' => $LNG['st_history_no_data']);
        }

        if ($change == 0)
        {
            return array('color' => '#87CEEB', 'text' => $LNG['st_history_no_change']);
        }

        if ($isRank)
        {
            $sign = $change > 0 ? '&#9650; ' : '&#9660; ';
        }
        else
        {
            $sign = $change > 0 ? '+' : '-';
        }

        return array(
            'color' => $change > 0 ? 'green' : 'red',
            'text'  => $sign.pretty_number(abs($change)),
        );
    }
}