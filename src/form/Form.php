<?php

declare(strict_types=1);

namespace WinsomeQuill\FriendsList\Form;

require_once(__DIR__ . '/../provider/SQLite.php');
require_once(__DIR__ . '/../utils/Utils.php');

use WinsomeQuill\FriendsList\utils\Utils;
use WinsomeQuill\FriendsList\provider\SQLManager;
use FormAPI\window\SimpleWindowForm;
use FormAPI\window\CustomWindowForm;
use FormAPI\window\ModalWindowForm;
use FormAPI\elements\Button;
use pocketmine\player\Player;

class Form {
    public static $cachePlayer = [];

    public static function friendsListForm(Player $sender) : void {
        $list = SQLManager::friendsList($sender);

        if(count($list) === 0) {
            $window = new SimpleWindowForm("friendsListForm", "Friends List Menu", "Your friends list is empty! :(");
            $window->showTo($sender);
            return;
        }

        $window = new SimpleWindowForm("friendsListForm", "Friends List Menu", "Select player from friends list", function (Player $sender, Button $button) {
            $name = $button->getName();
            self::$cachePlayer[$sender->GetName()]['targetName'] = $name;
            self::friendManagementForm($sender, $name);
        });

        foreach ($list as $item) {
            $window->addButton($item, "[ {$item} ]");
        }
        $window->showTo($sender);
    }

    public static function friendManagementForm(Player $sender, string $name) : void {
        $window = new SimpleWindowForm("friendsManagementForm", "Friends List Menu", "Friend: §e{$name}§f", function (Player $sender, Button $button) {
            $name = $button->getName();
            switch ($name) {
                case "sendMessage":
                    self::sendMessageForm($sender);
                    break;

                case "removeFriend":
                    self::removeFriendForm($sender);
                    break;

                case "back":
                    self::friendsListForm($sender);
                    break;
            }
        });

        $window->addButton("sendMessage", "Send Message");
        $window->addButton("removeFriend", "§cRemove Friend");
        $window->addButton("back", "Back");
        $window->showTo($sender);
    }

    public static function sendMessageForm(Player $sender) : void {
        $targetName = self::$cachePlayer[$sender->GetName()]['targetName'];
        $window = new CustomWindowForm("friendsSendMessageForm", "Send Message Menu", "Print message for {$targetName}");
        $window->addInput("message", "Insert your message");
        $window->showTo($sender);
    }

    public static function removeFriendForm(Player $sender) : void {
        $targetName = self::$cachePlayer[$sender->GetName()]['targetName'];
        $window = new ModalWindowForm("removeFriendConfirm", "Remove Friend {$targetName}", 
            "You are sure you want to be removed {$targetName} from your friends?", "§cYes", "No");

        $window->showTo($sender);
    }
}