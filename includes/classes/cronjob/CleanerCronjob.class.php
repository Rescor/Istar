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

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class CleanerCronjob implements CronjobTask
{
	const BATCH_SIZE = 10000;

	// Deletes in batches so a large cleanup does not lock a table for long,
	// and the batches already done stay done if the process gets killed.
	private function deleteInBatches($sql, array $params = array())
	{
		$db	= Database::get();
		do {
			$db->delete($sql.' LIMIT '.self::BATCH_SIZE.';', $params);
		} while($db->rowCount() >= self::BATCH_SIZE);
	}

	function run()
	{
        $config	= Config::get(ROOT_UNI);

		$unis	= Universe::availableUniverses();
	
		//Delete old messages
		$del_before 	= TIMESTAMP - ($config->del_oldstuff * 86400);
		$del_inactive 	= TIMESTAMP - ($config->del_user_automatic * 86400);
		$del_deleted 	= TIMESTAMP - ($config->del_user_manually * 86400);
		$del_messages 	= TIMESTAMP - ($config->message_delete_days * 86400);

		// del_user_automatic = 0 means "never delete inactive players"
		if($del_inactive === TIMESTAMP)
		{
			$del_inactive = 0;
		}

		// Only system messages expire. Private (1), alliance (2) and news (50) messages
		// are kept until the player deletes them.
		$sql	= 'DELETE FROM %%MESSAGES%% WHERE `message_type` IN (0, 3, 4, 5, 15, 99) AND `message_time` < :time';
		$this->deleteInBatches($sql, array(
			':time'	=> $del_before
		));

		$sql	= 'DELETE FROM %%ALLIANCE%% WHERE `ally_members` = 0;';
		Database::get()->delete($sql);

		$sql	= 'DELETE FROM %%PLANETS%% WHERE `destruyed` < :time AND `destruyed` != 0;';
		Database::get()->delete($sql, array(
			':time'	=> TIMESTAMP
		));

		$sql	= 'DELETE FROM %%SESSION%% WHERE `lastonline` < :time;';
		Database::get()->delete($sql, array(
			':time'	=> TIMESTAMP - SESSION_LIFETIME
		));

		$sql	= 'DELETE FROM %%FLEETS_EVENT%% WHERE fleetID NOT IN (SELECT fleet_id FROM %%FLEETS%%);';
		Database::get()->delete($sql);

		$sql	= 'UPDATE %%USERS%% SET `email_2` = `email` WHERE `setmail` < :time;';
		Database::get()->update($sql, array(
			':time'	=> TIMESTAMP
		));

		$sql	= 'SELECT `id` FROM %%USERS%% WHERE `authlevel` = :authlevel
		AND ((`db_deaktjava` != 0 AND `db_deaktjava` < :timeDeleted) OR `onlinetime` < :timeInactive);';

		$deleteUserIds = Database::get()->select($sql, array(
			':authlevel'	=> AUTH_USR,
			':timeDeleted'	=> $del_deleted,
			':timeInactive'	=> $del_inactive
		));

		if(!empty($deleteUserIds))
		{
			foreach($deleteUserIds as $dataRow)
			{
				PlayerUtil::deletePlayer($dataRow['id']);
			}	
		}
		
		foreach($unis as $uni)
		{
			$sql	= 'SELECT units FROM %%TOPKB%% WHERE `universe` = :universe ORDER BY units DESC LIMIT 99,1;';

			$battleHallLowest	= Database::get()->selectSingle($sql, array(
				':universe'	=> $uni
			),'units');

			if(!is_null($battleHallLowest))
			{
				$sql	= 'DELETE %%TOPKB%%, %%TOPKB_USERS%%
				FROM %%TOPKB%%
				INNER JOIN %%TOPKB_USERS%% USING (rid)
				WHERE `universe` = :universe AND `units` < :battleHallLowest;';

				Database::get()->delete($sql, array(
					':universe'			=> $uni,
					':battleHallLowest'	=> $battleHallLowest
				));
			}
		}

		$sql	= 'DELETE FROM %%RW%% WHERE `time` < :time AND `rid` NOT IN (SELECT `rid` FROM %%TOPKB%%)';
		$this->deleteInBatches($sql, array(
			':time'	=> $del_before
		));

		$sql	= 'DELETE FROM %%MESSAGES%% WHERE `message_deleted` < :time';
		$this->deleteInBatches($sql, array(
			':time'	=> $del_messages
		));
	}
}