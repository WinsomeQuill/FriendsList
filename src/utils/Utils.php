<?php

declare(strict_types=1);

namespace WinsomeQuill\FriendsList\Utils;

use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

class Utils extends PluginBase {
    public static function findPlayer(PluginBase $plugin, string $name) : ?Player {
        foreach($plugin->getServer()->getOnlinePlayers() as $player) {
            if(strtolower($player->GetName()) == strtolower($name)) {
                return $player;
            }
        }
        return null;
    }
}
