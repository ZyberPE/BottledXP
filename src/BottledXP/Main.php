<?php

namespace BottledXP;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\VanillaItems;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerInteractEvent;

class Main extends PluginBase implements Listener{

    private Config $config;

    public function onEnable() : void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{

        if(!$sender instanceof Player){
            return true;
        }

        if(count($args) < 1){
            $sender->sendMessage($this->config->getNested("messages.usage"));
            return true;
        }

        $amount = (int)$args[0];

        if($amount <= 0){
            $sender->sendMessage($this->config->getNested("messages.invalid-amount"));
            return true;
        }

        if($sender->getXpManager()->getXpLevel() < $amount){
            $sender->sendMessage($this->config->getNested("messages.not-enough-xp"));
            return true;
        }

        $sender->getXpManager()->setXpLevel($sender->getXpManager()->getXpLevel() - $amount);

        $bottle = $this->createBottle($sender->getName(), $amount);

        $sender->getInventory()->addItem($bottle);

        $msg = str_replace("{amount}", $amount, $this->config->getNested("messages.success"));
        $sender->sendMessage($msg);

        return true;
    }

    public function createBottle(string $player, int $amount) : Item{

        $item = VanillaItems::EXPERIENCE_BOTTLE();

        $name = str_replace("{amount}", $amount, $this->config->get("bottle-name"));

        $item->setCustomName($name);

        $lore = [];

        foreach($this->config->get("lore") as $line){
            $line = str_replace("{amount}", $amount, $line);
            $line = str_replace("{player}", $player, $line);
            $lore[] = $line;
        }

        $item->setLore($lore);

        $nbt = $item->getNamedTag();
        $nbt->setInt("xp_amount", $amount);
        $item->setNamedTag($nbt);

        return $item;
    }

    public function onTap(PlayerInteractEvent $event) : void{

        $player = $event->getPlayer();
        $item = $event->getItem();

        if(!$item->getNamedTag()->getTag("xp_amount")){
            return;
        }

        $amount = $item->getNamedTag()->getInt("xp_amount");

        $player->getXpManager()->addXpLevels($amount);

        $item->pop();
        $player->getInventory()->setItemInHand($item);

        $msg = str_replace("{amount}", $amount, $this->config->getNested("messages.redeemed"));
        $player->sendMessage($msg);

        $event->cancel();
    }
}
