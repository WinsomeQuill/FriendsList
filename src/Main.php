<?php

declare(strict_types=1);

namespace WinsomeQuill\FriendsList;

require_once('provider/SQLite.php');

use WinsomeQuill\FriendsList\provider\SQLManager;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\event\player\PlayerJoinEvent;

class Main extends PluginBase implements Listener {
    
    private $cachePlayer = [];

    public function onEnable() : void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        SQLManager::Init($this->getDataFolder());
        $this->getLogger()->info("Friends List enabled!");
    }

    public function onDisable() : void {
        SQLManager::Close();
        $this->getLogger()->info("Friends List disabled!");
    }

    public function onPlayerJoin(PlayerJoinEvent $event) : void {
        $player = $event->getPlayer();
        if(!SQLManager::checkPlayer($player)) {
            SQLManager::createPlayer($player);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if($sender instanceof Player) {
            switch($command->getName()) {
                case "addfriend":
                    if(count($args) == 0) {
                        $sender->sendMessage("§f[§cError§f] Use: /addfriend <name>!");
                        break;
                    }

                    $target = $this->findPlayer($args[0]);
                    if($target == null) {
                        $sender->sendMessage("§f[§cError§f] Player §e\"{$args[0]}\"§f not found!");
                        break;
                    }

                    $this->requestFriend($sender, $target);
                    break;
                
                case "acceptfriend":
                    if(!$this->checkRequest($sender)) {
                        break;
                    }

                    $target = $this->cachePlayer[$sender->GetName()]['requestFrom'];
                    SQLManager::addFriend($sender, $target);
                    $sender->sendMessage("§f[§aSuccess§f] You have §aaccept§f request friend from \"{$target->GetName()}\"!");
                    $target->sendMessage("§f[§eInformation§f] Player \"{$sender->GetName()}\" accept your request friend!");
                    unset($this->cachePlayer[$sender->GetName()]['requestFrom']);
                    break;

                case "cancelfriend":
                    if(!$this->checkRequest($sender)) {
                        break;
                    }

                    $target = $this->cachePlayer[$sender->GetName()]['requestFrom'];
                    $sender->sendMessage("§f[§aSuccess§f] You have §creject§f request friend from \"{$target->GetName()}\"!");
                    $target->sendMessage("§f[§eInformation§f] Player \"{$sender->GetName()}\" §creject§f your request friend!");
                    unset($this->cachePlayer[$sender->GetName()]['requestFrom']);
                    break;

                case "friends":
                    $list = SQLManager::friendsList($sender);
                    if(count($list) == 0) {
                        $sender->sendMessage("§f[§cError§f] Your friends list is empty!");
                        break;
                    }

                    $msg = implode("\n §f- §e", $list);
                    $sender->sendMessage("Your friends list:\n §f- §e{$msg}");
                    break;

                case "removefriend":
                    if(count($args) == 0) {
                        $sender->sendMessage("§f[§cError§f] Use: /removefriend <name>!");
                        break;
                    }

                    if(!SQLManager::isFriend($sender, $args[0])) {
                        $sender->sendMessage("§f[§cError§f] Player §e\"{$args[0]}\"§f not found in your friends list!");
                        break;
                    }

                    if(SQLManager::removeFriend($sender, $args[0])) {
                        $sender->sendMessage("§f[§aSuccess§f] Player §e\"{$args[0]}\"§f removed from your friends list!");
                        $target = $this->findPlayer($args[0]);
                        if($target !== null) {
                            $target->sendMessage("§f[§eInforamtion§f] Player §e\"{$sender->GetName()}\"§f removed you from friends list!");
                        }
                    }
                    break;
            }
        }
        return true;
    }

    public function requestFriend(Player $sender, Player $target) : void {
        $time = new \DateTime();
        $this->cachePlayer[$target->GetName()]['requestFrom'] = $sender;
        $this->cachePlayer[$target->GetName()]['requestTime'] = $time->add(new \DateInterval('PT1M'));
        $sender->sendMessage("§f[§aSuccess§f] You have send request friend to player §e\"{$target->GetName()}\"§f!");
        $target->sendMessage("§f[§eInforamtion§f] Player {$sender->GetName()} send you request friend!");
        $target->sendMessage("§f[§eInforamtion§f] Print §e\"/acceptfriend\"§f - §aaccept§f request! §e\"/cancelfriend\"§f - §creject§f request!");
        $target->sendMessage("§f[§eInforamtion§f] After 1 minute, the request will expired!");
    }

    public function checkRequest(Player $sender) : bool {
        if(!isset($this->cachePlayer[$sender->GetName()]['requestTime']) || !isset($this->cachePlayer[$sender->GetName()]['requestFrom'])) {
            $sender->sendMessage("§f[§cError§f] There are no active requests!");
            return false;
        }

        $time = new \DateTime();
        if($this->cachePlayer[$sender->GetName()]['requestTime'] < $time) {
            $sender->sendMessage("§f[§cError§f] Request is time over!");
            unset($this->cachePlayer[$sender->GetName()]['requestTime']);
            return false;
        }

        return true;
    }

    public function findPlayer(string $name) : ?Player {
        foreach($this->getServer()->getOnlinePlayers() as $player) {
            if(strtolower($player->GetName()) == strtolower($name)) {
                return $player;
            }
        }
        return null;
    }
}
