<?php

declare(strict_types=1);

namespace Terpz710\DailyRewardsPE\Commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\Plugin;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\player\Player;

use DaPigGuy\PiggyCustomEnchants\enchants\CustomEnchantManager;
use Terpz710\BankNotesPlus\BankNotesPlus;
use Terpz710\DailyRewardsPE\Main;

class DailyCommand extends Command implements PluginOwned {

    private $plugin;
    private $bankNotesPlus;
    private $cooldowns;
    private $messages;

    public function __construct(Main $plugin, BankNotesPlus $bankNotesPlus) {
        parent::__construct("daily", "Claim your daily items");
        $this->plugin = $plugin;
        $this->bankNotesPlus = $bankNotesPlus;
        $this->cooldowns = $plugin->getCooldowns();
        $this->messages = $plugin->getMessagesConfig();
        $this->setPermission("dailyrewardsplus.command.daily");
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if ($sender instanceof Player) {
            $cooldownKey = "daily_cooldown_" . $sender->getName();
            $cooldown = $this->cooldowns->get($cooldownKey, 0);

            $currentTimestamp = time();

            if ($cooldown <= $currentTimestamp) {
                $cooldown = $currentTimestamp + 24 * 60 * 60;
                $this->cooldowns->set($cooldownKey, $cooldown);
                $this->cooldowns->save();

                $rewardsList = $this->getRewardList("items_daily");

                if (!empty($rewardsList)) {
                    $randomReward = $rewardsList[array_rand($rewardsList)];
                    $this->giveReward($sender, $randomReward);
                } else {
                    $sender->sendMessage($this->messages->get("no-daily-rewards"));
                }
            } else {
                $remainingTime = $cooldown - $currentTimestamp;
                $this->sendCooldownMessage($sender, "daily", $remainingTime, "cooldown-message-daily");
            }
        } else {
            $sender->sendMessage($this->messages->get("player-only-command"));
        }

        return true;
    }

    private function getRewardList(string $type): array {
        return $this->plugin->getRewardsConfig()->get($type, []);
    }

    private function giveReward(Player $player, array $rewardData) {
        if (isset($rewardData['is_bank_note']) && $rewardData['is_bank_note']) {
            $amount = $rewardData['amount'];
            $this->bankNotesPlus->convertToBankNote($player, $amount);
            $player->sendMessage(str_replace("{amount}", strval($amount), $this->messages->get("claimed-bank-note")));
        } else {
            $item = StringToItemParser::getInstance()->parse($rewardData['item']);

            if (isset($rewardData['enchantments'])) {
                foreach ($rewardData['enchantments'] as $enchantmentName => $enchantmentLevel) {
                    $enchantment = StringToEnchantmentParser::getInstance()->parse($enchantmentName);

                    if ($enchantment === null && class_exists(CustomEnchantManager::class)) {
                        $enchantment = CustomEnchantManager::getEnchantmentByName($enchantmentName);
                    }

                    if ($enchantment !== null) {
                        $enchantmentInstance = new EnchantmentInstance($enchantment, (int) $enchantmentLevel);
                        $item->addEnchantment($enchantmentInstance);
                    }
                }
            }

            if (isset($rewardData['custom_name'])) {
                $item->setCustomName($rewardData['custom_name']);
            }

            if (isset($rewardData['quantity'])) {
                $item->setCount($rewardData['quantity']);
            }

            $player->getInventory()->addItem($item);
            $player->sendMessage($this->messages->get("claimed-daily-item"));
        }
    }

    private function sendCooldownMessage(Player $player, string $type, int $remainingTime, string $messageKey) {
        $days = floor($remainingTime / (24 * 3600));
        $hours = floor(($remainingTime % (24 * 3600)) / 3600);
        $minutes = floor(($remainingTime % 3600) / 60);
        $seconds = $remainingTime % 60;

        $message = $this->messages->get($messageKey);
        $replaceArray = ["{type}", "{days}", "{hours}", "{minutes}", "{seconds}"];
        $replaceValues = [$type, $days, $hours, $minutes, $seconds];
        $formattedMessage = str_replace($replaceArray, $replaceValues, $message);

        $player->sendMessage($formattedMessage);
    }
}
