<?php

namespace aieuo\mfconverter;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase {

    public function onEnable() {
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        return true;
    }
}