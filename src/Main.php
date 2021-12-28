<?php

declare(strict_types=1);

namespace WinsomeQuill\FriendsList;

require_once('provider/SQLite.php');
require_once('form/Form.php');
require_once('utils/Utils.php');

use WinsomeQuill\FriendsList\provider\SQLManager;
use WinsomeQuill\FriendsList\form\Form;
use WinsomeQuill\FriendsList\utils\Utils;
use FormAPI\response\PlayerWindowResponse;
use FormAPI\window\SimpleWindowForm;
use FormAPI\window\CustomWindowForm;
use FormAPI\window\ModalWindowForm;
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

                    if($sender->GetName() === $args[0]) {
                        $sender->sendMessage("§f[§cError§f] You can't add yourself in your friend list!");
                        break;
                    }

                    $target = Utils::findPlayer($this, $args[0]);
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
                    $form = new Form();
                    $form->friendsListForm($sender);
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

    public function onResponse(PlayerWindowResponse $event): void {
        $sender = $event->getPlayer();
        $form = $event->getForm();
    
        if($form instanceof SimpleWindowForm && $form->getName() == "friendsListForm") {
            unset(Form::$cachePlayer[$sender->GetName()]['targetName']);
            return;
        }

        if ($form instanceof CustomWindowForm && $form->getName() === "friendsSendMessageForm") {
            $message = $form->getElement("message")->getFinalValue();
            $targetName = Form::$cachePlayer[$sender->GetName()]['targetName'];
            $target = Utils::findPlayer($this, $targetName);
            if($target === null) {
                $sender->sendMessage("§f[§cError§f] Your friend is offline!");
                return;
            }
            
            $sender->sendMessage("§f[§aSuccess§f] You have successfully sent a message!");
            $target->sendMessage("§f[§eInformation§f] Friend §e\"{$sender->GetName()}\"§f sended you message -> §a{$message}");
            Form::friendManagementForm($sender, $targetName);
            return;
        }

        if($form instanceof ModalWindowForm && $form->getName() === "removeFriendConfirm") {
            $targetName = Form::$cachePlayer[$sender->GetName()]['targetName'];
            if($form->isAccept()) {
                SQLManager::removeFriend($sender, $targetName);

                $target = Utils::findPlayer($this, $targetName);
                $sender->sendMessage("§f[§aSuccess§f] Player §e\"{$targetName}\"§f removed from your friends list!");
                if($target !== null) {
                    $target->sendMessage("§f[§eInforamtion§f] Player §e\"{$sender->GetName()}\"§f removed you from friends list!");
                    return;
                }

                Form::friendsListForm($sender);
                return;
            } else {
                Form::friendManagementForm($sender, $targetName);
                return;
            }
        }
    }
}
