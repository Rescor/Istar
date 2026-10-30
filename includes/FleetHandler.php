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

$token	= getRandomString();
$db		= Database::get();

// A handler killed by the host (time or memory limit, lost database connection) never
// releases its lock. Release such batches after 10 minutes so their fleets are processed
// again, but after the third attempt lock a fleet for the admin: it may be the one that
// kills the handler.
$abandonedTime	= TIMESTAMP - 600;

$db->update("UPDATE %%FLEETS%% SET fleet_busy = 1 WHERE fleet_id IN (SELECT fleetID FROM %%FLEETS_EVENT%%
WHERE `lock` != 'ADM_LOCK' AND lockTime < :abandonedTime AND lockRetries >= 2);", array(
	':abandonedTime'	=> $abandonedTime
));

$db->update("UPDATE %%FLEETS_EVENT%% SET `lock` = 'ADM_LOCK'
WHERE `lock` != 'ADM_LOCK' AND lockTime < :abandonedTime AND lockRetries >= 2;", array(
	':abandonedTime'	=> $abandonedTime
));
$lockedCount	= $db->rowCount();

$db->update("UPDATE %%FLEETS_EVENT%% SET `lock` = NULL, lockTime = NULL, lockRetries = lockRetries + 1
WHERE `lock` != 'ADM_LOCK' AND lockTime < :abandonedTime;", array(
	':abandonedTime'	=> $abandonedTime
));
$releasedCount	= $db->rowCount();

if($lockedCount !== 0 || $releasedCount !== 0) {
	error_log(sprintf('Fleet handler: released %d abandoned fleet events, locked %d for admin after 3 attempts',
		$releasedCount, $lockedCount));
}

$fleetResult	= $db->update("UPDATE %%FLEETS_EVENT%% SET `lock` = :token, lockTime = :lockTime WHERE `lock` IS NULL AND `time` <= :time;", array(
	':time'		=> TIMESTAMP,
	':lockTime'	=> TIMESTAMP,
	':token'	=> $token
));

if($db->rowCount() !== 0) {
	require 'includes/classes/class.FlyingFleetHandler.php';
	
	$fleetObj	= new FlyingFleetHandler();
	$fleetObj->setToken($token);
	$fleetObj->run();

	$db->update("UPDATE %%FLEETS_EVENT%% SET `lock` = NULL, lockTime = NULL, lockRetries = 0 WHERE `lock` = :token;", array(
		':token' => $token
	));
}