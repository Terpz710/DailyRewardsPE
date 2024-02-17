<?php

declare(strict_types=1);

namespace Terpz710\DailyRewardsPE;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;

use Terpz710\BankNotesPlus\BankNotesPlus;
use Terpz710\DailyRewardsPE\Commands\DailyCommand;
use Terpz710\DailyRewardsPE\Commands\WeeklyCommand;
use Terpz710\DailyRewardsPE\Commands\MonthlyCommand;

class Main extends PluginBase implements Listener {

    /** @var Config */
    private $config;
    /** @var Config */
    private $cooldowns;
    /** @var Config */
    private $messages;

    public function onEnable(): void {
        $this->saveResource("rewards.yml");
        $this->config = new Config($this->getDataFolder() . "rewards.yml", Config::YAML);

        $this->saveResource("messages.yml");
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);

        $cooldownsFolder = $this->getDataFolder() . "cooldowns";
        if (!is_dir($cooldownsFolder)) {
            mkdir($cooldownsFolder);
        }
        $cooldownsPath = $cooldownsFolder . DIRECTORY_SEPARATOR . "cooldowns.json";
        $this->cooldowns = new Config($cooldownsPath, Config::JSON);

        $this->getServer()->getCommandMap()->registerAll("DailyRewardsPE", [
			    new DailyCommand($this),
			    new WeeklyCommand($this),
                            new MonthlyCommand($this)
		    ]);

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->initializePlayerData($player);
    }

    public function getRewardsConfig(): Config {
        return $this->config;
    }

    public function getCooldowns(): Config {
        return $this->cooldowns;
    }

    public function getMessagesConfig(): Config {
        return $this->messages;
    }

    private function initializePlayerData($player) {
        $dailyCooldownKey = "daily_cooldown_" . $player->getName();
        $weeklyCooldownKey = "weekly_cooldown_" . $player->getName();
        $monthlyCooldownKey = "monthly_cooldown_" . $player->getName();

        if (!$this->cooldowns->exists($dailyCooldownKey)) {
            $this->cooldowns->set($dailyCooldownKey, 0);
            $this->cooldowns->save();
        }

        if (!$this->cooldowns->exists($weeklyCooldownKey)) {
            $this->cooldowns->set($weeklyCooldownKey, 0);
            $this->cooldowns->save();
        }

        if (!$this->cooldowns->exists($monthlyCooldownKey)) {
            $this->cooldowns->set($monthlyCooldownKey, 0);
            $this->cooldowns->save();
        }
    }
}
