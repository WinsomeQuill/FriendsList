<?php

declare(strict_types=1);

namespace WinsomeQuill\FriendsList\provider;

use pocketmine\player\Player;

class SQLManager {
    private static \SQLite3 $db;

    public static function Init(string $folder) {
        self::$db = new \SQLite3($folder . "friendslist.db");
        self::$db->exec("
            CREATE TABLE IF NOT EXISTS players (
                id	INTEGER NOT NULL,
                Name	NVARCHAR(255) NOT NULL,
                Privated	INTEGER NOT NULL DEFAULT '0',
                PRIMARY KEY(id AUTOINCREMENT)
            );

            CREATE TABLE IF NOT EXISTS players_friends (
                player_id	INTEGER NOT NULL,
                friend_id	INTEGER NOT NULL,
                FOREIGN KEY(friend_id) REFERENCES players(id),
                FOREIGN KEY(player_id) REFERENCES players(id)
            );
        ");
    }

    public static function Close() {
        if(isset(self::$db))  {
            self::$db->close();
        }
    }

    public static function checkPlayer(Player $player) : bool {
        $result = self::$db->querySingle("SELECT id FROM players WHERE Name = '{$player->GetName()}';");
        if($result === false || is_null($result)) {
            return false;
        }
        return true;
    }

    public static function createPlayer(Player $player) : void {
        self::$db->exec("
            INSERT INTO players (Name) VALUES ('{$player->GetName()}');
        ");
    }

    public static function addFriend(Player $player, Player $friend) : bool {
        $result = self::$db->exec("
            INSERT INTO players_friends (player_id, friend_id) 
            VALUES ((SELECT id FROM players WHERE Name = '{$player->GetName()}'), (SELECT id FROM players WHERE Name = '{$friend->GetName()}'));
        ");

        if($result === false) {
            return false;
        }
        return true;
    }

    public static function removeFriend(Player $player, string $friend) : bool {
        $result = self::$db->exec("
            DELETE FROM players_friends 
            WHERE player_id = (SELECT id FROM players WHERE Name = '{$player->GetName()}')
            AND
            friend_id = (SELECT id FROM players WHERE Name = '{$friend}');
        ");

        if($result === false) {
            return false;
        }
        return true;
    }

    public static function isPrivated(Player $player) : bool {
        $result = self::$db->exec("
            SELECT Privated FROM players
            WHERE Name = '{$player->GetName()}';
        ");

        if($result === '0') {
            return false;
        }
        return true;
    }

    public static function friendsList(Player $player) {
        $list = [];
        $result = self::$db->query("
            SELECT p.Name FROM players AS p, players_friends AS pf
            WHERE pf.player_id = (SELECT id FROM players WHERE Name = '{$player->GetName()}')
            AND pf.friend_id = p.id
        ");

        while($row = $result->fetchArray(SQLITE3_ASSOC)) {
            array_push($list, $row['Name']);
        }
        return $list;
    }

    public static function isFriend(Player $player, string $friend) : bool {
        $result = self::$db->querySingle("
            SELECT id FROM players AS p, players_friends AS pf
            WHERE pf.player_id = (SELECT id FROM players WHERE Name = '{$player->GetName()}')
            AND pf.friend_id = (SELECT id FROM players WHERE Name = '{$friend}')
            AND p.id = pf.friend_id;
        ");

        echo $result . "\n";

        if($result === false || is_null($result)) {
            return false;
        }
        return true;
    }
}