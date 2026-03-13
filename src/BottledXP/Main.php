<?php

namespace BottledXP;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\player\Player;

use pocketmine\item\VanillaItems;
use pocketmine\item\Item;

use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\entity\projectile\ExperienceBottle;

use pocketmine\network\mcpe\protocol\PlaySoundPacket;

class Main extends PluginBase implements Listener{

    private Config $config;

    public function onEnable() : void{
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        $this->getServer()->getPluginManager()->registerEvents($this,$this);
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
            $sender->sendMessage($this->config->getNested("messages.invalid"));
            return true;
        }

        $xpManager = $sender->getXpManager();

        if($xpManager->getXpLevel() < $amount){
            $sender->sendMessage($this->config->getNested("messages.not-enough-xp"));
            return true;
        }

        $xpManager->setXpLevel($xpManager->getXpLevel() - $amount);

        $item = $this->createBottle($sender,$amount);

        $sender->getInventory()->addItem($item);

        $msg = str_replace("{amount}",$amount,$this->config->getNested("messages.bottled"));
        $sender->sendMessage($msg);

        return true;
    }

    private function createBottle(Player $player,int $amount) : Item{

        $item = VanillaItems::EXPERIENCE_BOTTLE();

        $item->setCustomName($this->config->get("bottle-name"));

        $lore = [];

        foreach($this->config->get("lore") as $line){
            $line = str_replace("{amount}",$amount,$line);
            $line = str_replace("{player}",$player->getName(),$line);
            $lore[] = $line;
        }

        $item->setLore($lore);

        $tag = $item->getNamedTag();
        $tag->setInt("xp_amount",$amount);
        $item->setNamedTag($tag);

        return $item;
    }

    public function onBottleThrow(ProjectileLaunchEvent $event) : void{

        $entity = $event->getEntity();

        if(!$entity instanceof ExperienceBottle){
            return;
        }

        $owner = $entity->getOwningEntity();

        if(!$owner instanceof Player){
            return;
        }

        $item = $owner->getInventory()->getItemInHand();

        if(!$item->getNamedTag()->getTag("xp_amount")){
            return;
        }

        $event->cancel();

        $amount = $item->getNamedTag()->getInt("xp_amount");

        $xpManager = $owner->getXpManager();

        $currentLevel = $xpManager->getXpLevel();
        $maxLevel = $this->config->get("max-levels");

        if($currentLevel >= $maxLevel){
            $owner->sendMessage($this->config->getNested("messages.max-level"));
            return;
        }

        $newLevel = $currentLevel + $amount;

        if($newLevel > $maxLevel){
            $amount = $maxLevel - $currentLevel;
        }

        $xpManager->setXpLevel($currentLevel + $amount);

        $item->setCount($item->getCount() - 1);
        $owner->getInventory()->setItemInHand($item);

        $msg = str_replace("{amount}",$amount,$this->config->getNested("messages.redeemed"));
        $owner->sendMessage($msg);

        $this->playSound($owner,$this->config->getNested("sounds.redeem"));
    }

    private function playSound(Player $player,string $sound) : void{

        $pk = new PlaySoundPacket();

        $pk->soundName = $sound;
        $pk->x = $player->getPosition()->getX();
        $pk->y = $player->getPosition()->getY();
        $pk->z = $player->getPosition()->getZ();
        $pk->volume = 1;
        $pk->pitch = 1;

        $player->getNetworkSession()->sendDataPacket($pk);
    }
}
