<?php
require_once __dir__."/../private/site.php";

$coffee_conn->setDebug(false);
$nextmeeting=get_adjacent_meeting_times("start", true, time());
$nextmeeting=date("Y-m-d H:i", $nextmeeting['next']);

// only want to return one short message.
$message = array();

// don't really care about CSRF here.
if ($user->isLoggedIn()) {
    $paperId = intval($params->get("paperId"));
    $bump = intval($params->get("bump"));
    // make sure the paper exists
    $statement = "SELECT * FROM papers WHERE id = ? LIMIT 1";
    $result = $coffee_conn->boundQuery($statement, array('i', &$paperId));
    if (!count($result)) {
        $message["error"] = "Uh-oh, something went wrong. Please try later.";
    } else {
        $statement = "SELECT id FROM votes WHERE paperId = ? AND userId = 'bump'";
        $result = $coffee_conn->boundQuery($statement, array('i', &$paperId));
        if (count($result)) {
            $voteId = intval($result[0]['id']);
            if ($bump) {
                $statement = "UPDATE votes SET date= ? WHERE id = ?";
                $coffee_conn->boundCommand($statement, array('si', &$nextmeeting, &$voteId));
                $message["success"] = "Paper bumped again to next meeting " . $nextmeeting .".";
            } else {
                $statement = "DELETE FROM votes WHERE id = ?";
                $coffee_conn->boundCommand($statement, array('i', &$voteId));
                $message["success"] = "Paper no longer bumped.";
            }
        } else {
            if ($bump) {
                // New bump.
                $statement = "INSERT INTO votes (paperId, userId, value, date) VALUES(?, 'bump', 0, ?)";
                $coffee_conn->boundCommand($statement, array('is', &$paperId, &$nextmeeting));
                $message["success"] = "Paper bumped to next meeting " . $nextmeeting .".";
            } else {
                $message["success"] = "Nothing changed.";
            }
        }
    }
} else {
    $message["error"] = "You must <a href='" . path() . "login'>sign in</a> before you can bump!";
}

print json_encode($message);
