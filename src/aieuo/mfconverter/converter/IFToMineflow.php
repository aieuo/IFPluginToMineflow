<?php

namespace aieuo\mfconverter\converter;

use aieuo\ip\conditions\Comparison;
use aieuo\ip\conditions\ConditionIds;
use aieuo\ip\IFPlugin;
use aieuo\ip\processes\ProcessIds;
use aieuo\mfconverter\Main;
use aieuo\mineflow\flowItem\action\Action;
use aieuo\mineflow\flowItem\action\AddDamage;
use aieuo\mineflow\flowItem\action\AddEffect;
use aieuo\mineflow\flowItem\action\AddEnchantment;
use aieuo\mineflow\flowItem\action\AddItem;
use aieuo\mineflow\flowItem\action\AddMoney;
use aieuo\mineflow\flowItem\action\AddParticle;
use aieuo\mineflow\flowItem\action\AddVariable;
use aieuo\mineflow\flowItem\action\BroadcastMessage;
use aieuo\mineflow\flowItem\action\ClearInventory;
use aieuo\mineflow\flowItem\action\Command;
use aieuo\mineflow\flowItem\action\CommandConsole;
use aieuo\mineflow\flowItem\action\CreateBlockVariable;
use aieuo\mineflow\flowItem\action\CreateItemVariable;
use aieuo\mineflow\flowItem\action\CreatePositionVariable;
use aieuo\mineflow\flowItem\action\DeleteVariable;
use aieuo\mineflow\flowItem\action\DoNothing;
use aieuo\mineflow\flowItem\action\ElseAction;
use aieuo\mineflow\flowItem\action\ElseifAction;
use aieuo\mineflow\flowItem\action\EventCancel;
use aieuo\mineflow\flowItem\action\ExecuteRecipe;
use aieuo\mineflow\flowItem\action\ExecuteRecipeWithEntity;
use aieuo\mineflow\flowItem\action\FourArithmeticOperations;
use aieuo\mineflow\flowItem\action\GenerateRandomNumber;
use aieuo\mineflow\flowItem\action\GetInventoryContents;
use aieuo\mineflow\flowItem\action\GetPlayerByName;
use aieuo\mineflow\flowItem\action\GetVariableNested;
use aieuo\mineflow\flowItem\action\IFAction;
use aieuo\mineflow\flowItem\action\Kick;
use aieuo\mineflow\flowItem\action\Motion;
use aieuo\mineflow\flowItem\action\PlaySound;
use aieuo\mineflow\flowItem\action\RemoveBossbar;
use aieuo\mineflow\flowItem\action\RemoveItem;
use aieuo\mineflow\flowItem\action\RepeatAction;
use aieuo\mineflow\flowItem\action\SendForm;
use aieuo\mineflow\flowItem\action\SendMessage;
use aieuo\mineflow\flowItem\action\SendMessageToOp;
use aieuo\mineflow\flowItem\action\SendTip;
use aieuo\mineflow\flowItem\action\SendTitle;
use aieuo\mineflow\flowItem\action\SetBlock;
use aieuo\mineflow\flowItem\action\SetFood;
use aieuo\mineflow\flowItem\action\SetGamemode;
use aieuo\mineflow\flowItem\action\SetHealth;
use aieuo\mineflow\flowItem\action\SetImmobile;
use aieuo\mineflow\flowItem\action\SetItemCount;
use aieuo\mineflow\flowItem\action\SetItemDamage;
use aieuo\mineflow\flowItem\action\SetItemInHand;
use aieuo\mineflow\flowItem\action\SetItemLore;
use aieuo\mineflow\flowItem\action\SetItemName;
use aieuo\mineflow\flowItem\action\SetMaxHealth;
use aieuo\mineflow\flowItem\action\SetNameTag;
use aieuo\mineflow\flowItem\action\SetScale;
use aieuo\mineflow\flowItem\action\SetSitting;
use aieuo\mineflow\flowItem\action\ShowBossbar;
use aieuo\mineflow\flowItem\action\TakeMoney;
use aieuo\mineflow\flowItem\action\Teleport;
use aieuo\mineflow\flowItem\action\UnsetImmobile;
use aieuo\mineflow\flowItem\action\Wait;
use aieuo\mineflow\flowItem\condition\CanAddItem;
use aieuo\mineflow\flowItem\condition\CheckNothing;
use aieuo\mineflow\flowItem\condition\ComparisonNumber;
use aieuo\mineflow\flowItem\condition\ComparisonString;
use aieuo\mineflow\flowItem\condition\Condition;
use aieuo\mineflow\flowItem\condition\ExistsItem;
use aieuo\mineflow\flowItem\condition\ExistsVariable;
use aieuo\mineflow\flowItem\condition\Gamemode;
use aieuo\mineflow\flowItem\condition\InHand;
use aieuo\mineflow\flowItem\condition\IsFlying;
use aieuo\mineflow\flowItem\condition\IsOp;
use aieuo\mineflow\flowItem\condition\IsSneaking;
use aieuo\mineflow\flowItem\condition\LessMoney;
use aieuo\mineflow\flowItem\condition\OverMoney;
use aieuo\mineflow\flowItem\condition\RandomNumber;
use aieuo\mineflow\flowItem\condition\RemoveItem as RemoveItemCondition;
use aieuo\mineflow\flowItem\condition\TakeMoney as TakeMoneyCondition;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\recipe\RecipeManager;
use aieuo\mineflow\Main as MineflowMain;
use aieuo\mineflow\trigger\Trigger;
use aieuo\mineflow\variable\ListVariable;
use aieuo\mineflow\variable\NumberVariable;
use aieuo\mineflow\variable\StringVariable;
use aieuo\mineflow\variable\Variable;
use pocketmine\utils\Config;

class IFToMineflow extends Converter {

    /* @var string */
    private $recipeDir;

    public function __construct(Main $owner) {
        parent::__construct($owner, $owner->getDataFolder()."/if2mf/");
        $this->recipeDir = $this->getBaseDir()."recipes/";
        if (!file_exists($this->recipeDir)) @mkdir($this->recipeDir, 0777, true);
    }

    public function convert() {
        $this->convertBlockIFs();
        $this->convertChainIFs();
        $this->convertCommandIFs();
        $this->convertFormIFs();
        $this->convertEventIFs();
        $this->convertVariables();
    }

    private function convertIF(array $ifData, Recipe $baseRecipe, bool $ifElseif = false): ?Recipe {
        $conditions = $ifData["if"];
        $actions1 = $ifData["match"];
        $actions2 = $ifData["else"];

        if (empty($conditions) and empty($actions2)) {
            foreach ($actions1 as $item) {
                $actions = $this->convertAction($item["id"], $item["content"]);
                if (empty($actions)) return null;
                foreach ($actions as $action) {
                    $baseRecipe->addAction($action);
                }
            }
            return $baseRecipe;
        }

        $ifAction = $ifElseif ? new ElseifAction() : new IFAction();
        foreach ($conditions as $item) {
            $conditions = $this->convertCondition($item["id"], $item["content"]);
            if (empty($conditions)) return null;
            foreach ($conditions as $condition) {
                $ifAction->addCondition($condition);
            }
        }
        foreach ($actions1 as $item) {
            $actions = $this->convertAction($item["id"], $item["content"]);
            if (empty($actions)) return null;
            foreach ($actions as $action) {
                $ifAction->addAction($action);
            }
        }
        $baseRecipe->addAction($ifAction);

        if (!empty($actions2)) {
            $elseAction = new ElseAction();
            foreach ($actions2 as $item) {
                $actions = $this->convertAction($item["id"], $item["content"]);
                if (empty($actions)) return null;
                foreach ($actions as $action) {
                    $elseAction->addAction($action);
                }
            }
            $baseRecipe->addAction($elseAction);
        }
        return $baseRecipe;
    }

    private function convertBlockIFs() {
        $blockManager = IFPlugin::getInstance()->getBlockManager();
        $ifs = $blockManager->getAll();

        foreach ($ifs as $name => $ifData) {
            $this->getLogger()->info("ブロックIF -> レシピ: ".$name);
            $baseRecipe = new Recipe($name, "block");
            $baseRecipe->addTrigger(new Trigger(Trigger::TYPE_BLOCK, $name));

            $recipe = $this->convertIF($ifData, $baseRecipe);
            if ($recipe === null) continue;

            $recipe->save($this->recipeDir);
        }
    }

    private function convertChainIFs() {
        $blockManager = IFPlugin::getInstance()->getChainManager();
        $ifs = $blockManager->getAll();

        foreach ($ifs as $name => $ifData) {
            $this->getLogger()->info("チェーンIF -> レシピ: ".$name);
            $baseRecipe = new Recipe($name, "chain");

            $recipe = $this->convertIF($ifData, $baseRecipe);
            if ($recipe === null) continue;

            $recipe->save($this->recipeDir);
        }
    }

    private function convertCommandIFs() {
        $blockManager = IFPlugin::getInstance()->getCommandManager();
        $ifs = $blockManager->getAll();
        $manager = MineflowMain::getCommandManager();
        $config = new Config($this->getBaseDir()."commands.yml", Config::YAML);

        foreach ($ifs as $name => $ifData) {
            $this->getLogger()->info("コマンドIF -> レシピ: ".$name);
            $baseRecipe = new Recipe($name, "command");
            $baseRecipe->addTrigger(new Trigger(Trigger::TYPE_COMMAND, $manager->getOriginCommand($name), $name));

            $permissions = [
                "ifplugin.customcommand.true" => "mineflow.customcommand.true",
                "ifplugin.customcommand.op" => "mineflow.customcommand.op"
            ];
            $origin = $manager->getOriginCommand($name);
            $subCommands = $manager->getSubcommandsFromCommand($name);

            $command = [
                "command" => $origin,
                "permission" => $permissions[$ifData["permission"]] ?? "mineflow.customcommand.op",
                "description" => $ifData["description"] ?? "",
                "subcommands" => $subCommands,
            ];
            $config->set($origin, $command);

            $recipe = $this->convertIF($ifData, $baseRecipe);
            $recipe->save($this->recipeDir);
        }
        $config->save();
    }

    private function convertFormIFs() {
        $blockManager = IFPlugin::getInstance()->getFormIFManager();
        $forms = $blockManager->getAll();
        $config = new Config($this->getBaseDir()."forms.json", Config::JSON);
        $config->setJsonOptions(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING);

        foreach ($forms as $formName => $formData) {
            $this->getLogger()->info("IFフォーム -> Mineflowフォーム: ".$formName);
            $form = Form::createFromArray(json_decode($formData["form"], true));
            $config->set($formName, [
                "name" => $formName,
                "type" => $form->getType(),
                "form" => $form,
            ]);

            if (empty($formData["ifs"])) continue;

            $baseRecipe = new Recipe($formName, "form");
            $baseRecipe->addTrigger(new Trigger(Trigger::TYPE_FORM, $formName));

            if (count($formData["ifs"]) === 1) {
                $ifData = array_shift($formData["ifs"]);
                $this->getLogger()->info("フォームIF -> レシピ: ".$formName.", ".($ifData["name"] ?? ""));

                $recipe = $this->convertIF($ifData, $baseRecipe);
                if ($recipe === null) continue;

                $recipe->save($this->recipeDir);
                continue;
            }

            $first = false;
            $actions = [];
            foreach ($formData["ifs"] as $name => $ifData) {
                $name = empty($ifData["name"]) ? $name : $ifData["name"];
                $this->getLogger()->info("フォームIF -> レシピ: ".$formName.", ".($ifData["name"] ?? ""));

                if (empty($ifData["if"]) or !empty($ifData["else"])) {
                    $recipe = $this->convertIF($ifData, clone $baseRecipe);
                    if ($recipe === null) continue 2;

                    $recipe->setName($name);
                    $recipe->setGroup("form/".$formName);
                    $recipe->save($this->recipeDir);
                    continue;
                }

                $recipe = $this->convertIF($ifData, clone $baseRecipe, $first);
                if ($recipe === null) continue 2;

                $actions = array_merge($actions, $recipe->getActions());

                $first = (bool)($first | true);
            }

            if (!empty($actions)) {
                $recipe = clone $baseRecipe;
                $recipe->setActions($actions);
                $recipe->save($this->recipeDir);
            }
        }
        $config->save();
    }

    private function convertEventIFs() {
        $blockManager = IFPlugin::getInstance()->getEventManager();
        $events = $blockManager->getAll();

        foreach ($events as $event => $ifs) {
            foreach ($ifs as $name => $ifData) {
                $name = empty($ifData["name"]) ? $name : $ifData["name"];
                $this->getLogger()->info("イベントIF -> レシピ: ".$event.", ".$name);
                $baseRecipe = new Recipe($name, "event/".$event);
                $baseRecipe->addTrigger(new Trigger(Trigger::TYPE_EVENT, $event));

                $recipe = $this->convertIF($ifData, $baseRecipe);
                if ($recipe === null) continue;

                $recipe->save($this->recipeDir."event/");
            }
        }
    }

    private function convertVariables() {
        $oldHelper = IFPlugin::getInstance()->getVariableHelper();
        $config = new Config($this->getBaseDir()."variables.json", Config::JSON);

        foreach ($oldHelper->getAll() as $variable) {
            /** @var \aieuo\ip\variable\Variable $variable */
            $this->getLogger()->info("IF変数 -> Mineflow変数: ".$variable->getName());
            switch ($variable->getType()) {
                case \aieuo\ip\variable\Variable::STRING:
                    $newVariable = new StringVariable($variable->getValue(), $variable->getName());
                    break;
                case \aieuo\ip\variable\Variable::NUMBER:
                    $newVariable = new NumberVariable($variable->getValue(), $variable->getName());
                    break;
                case \aieuo\ip\variable\Variable::LIST:
                    $newVariable = new ListVariable($variable->getValue(), $variable->getName());
                    break;
                default:
                    $this->getLogger()->error("変換に失敗しました (不明な変数のタイプです): ".(string)$variable);
                    return;
            }
            $config->set($newVariable->getName(), $newVariable);
        }
        $config->save();
    }

    /**
     * @param int $id
     * @param string $content
     * @return Action[]
     */
    private function convertAction(int $id, string $content): array {
        $content = $this->convertContent($content);
        switch ($id) {
            case ProcessIds::COMMAND:
                $actions = [new Command("target", $content)];
                break;
            case ProcessIds::SENDMESSAGE:
                $actions = [new SendMessage("target", $content)];
                break;
            case ProcessIds::SENDTIP:
                $actions = [new SendTip("target", $content)];
                break;
            case ProcessIds::TELEPORT:
                $data = $this->parseIfPosition($content);
                if (count($data) === 1) {
                    $actions = [new Teleport("target", "{createPosition(".$data[0].")}")];
                } else {
                    $actions = [new CreatePositionVariable(...$data), new Teleport()];
                }
                break;
            case ProcessIds::BROADCASTMESSAGE:
                $actions = [new BroadcastMessage($content)];
                break;
            case ProcessIds::COMMAND_CONSOLE:
                $actions = [new CommandConsole($content)];
                break;
            case ProcessIds::DO_NOTHING:
                $actions = [new DoNothing()];
                break;
            case ProcessIds::ADD_ITEM:
            case ProcessIds::REMOVE_ITEM:
                $item = $this->parseIfItem($content);
                $actions = [new CreateItemVariable($item[0], $item[1], $item[2])];
                if (!empty($item[3])) {
                    $actions[] = new SetItemLore("item", $item[3]);
                }
                if (!empty($item[4])) {
                    foreach (explode(";", $item[4]) as $enchant1) {
                        $enchants = explode(",", trim($enchant1));
                        $actions[] = new AddEnchantment("item", trim($enchants[0]), trim($enchants[1] ?? "1"));
                    }
                }
                switch ($id) {
                    case ProcessIds::ADD_ITEM:
                        $actions[] = new AddItem();
                        break;
                    case ProcessIds::REMOVE_ITEM:
                        $actions[] = new RemoveItem();
                        break;
                }
                break;
            case ProcessIds::SET_IMMOBILE:
                $actions = [new SetImmobile()];
                break;
            case ProcessIds::UNSET_IMMOBILE:
                $actions = [new UnsetImmobile()];
                break;
            case ProcessIds::SET_HEALTH:
                $actions = [new SetHealth("target", $content)];
                break;
            case ProcessIds::SET_MAXHEALTH:
                $actions = [new SetMaxHealth("target", $content)];
                break;
            case ProcessIds::SET_GAMEMODE:
                $actions = [new SetGamemode("target", (string)$content)];
                break;
            case ProcessIds::SET_NAMETAG:
                $actions = [new SetNameTag("target", $content)];
                break;
            case ProcessIds::ADD_ENCHANTMENT:
                $actions = [new GetVariableNested("target.hand", "item")];
                foreach (explode(";", $content) as $enchant1) {
                    $enchants = explode(",", trim($enchant1));
                    $actions[] = new AddEnchantment("item", trim($enchants[0]), trim($enchants[1] ?? "1"));
                }
                $actions[] = new SetItemInHand();
                break;
            case ProcessIds::ADD_EFFECT:
                $data = explode(",", $content);
                $actions = [new AddEffect("target", $data[0], $data[2], $data[1])];
                break;
            case ProcessIds::SEND_MESSAGE_TO_OP:
                $actions = [new SendMessageToOp($content)];
                break;
            case ProcessIds::SET_SITTING:
                $data = $this->parseIfPosition($content);
                if (count($data) === 1) {
                    $actions = [new SetSitting("target", "{createPosition(".$data[0].")}")];
                } else {
                    $actions = [new CreatePositionVariable(...$data), new SetSitting()];
                }
                break;
            case ProcessIds::ATTACK:
                $actions = [new AddDamage("target", (string)$content)];
                break;
            case ProcessIds::KICK:
                $actions = [new Kick("target", $content)];
                break;
            case ProcessIds::SENDTITLE:
                $data = (new \aieuo\ip\processes\SendTitle())->parse($content);
                $actions = [new SendTitle("target", ...$data)];
                break;
            case ProcessIds::MOTION:
                $data = explode(",", $content);
                $actions = [new Motion("target", $data[0], $data[1], $data[2])];
                break;
            case ProcessIds::DELAYED_COMMAND:
                $this->addDelayedCommand();
                $actions = [new ExecuteRecipe("aieuo/functions/delayedCommand", $content.",{target}")];
                break;
            case ProcessIds::CALCULATION:
                if (!preg_match("/\s*(.+)\s*\[ope:([0-9])]\s*(.+)\s*;\s*([^;]*)\s*$/", $content, $matches)) {
                    $this->getLogger()->error("変換に失敗しました (フォーマットが正しくありません): ".$id.", ".$content);
                    return [];
                }
                $operator = (int)$matches[2];
                $value1 = trim(rtrim($matches[1]));
                $value2 = trim(rtrim($matches[3]));
                $assign = $matches[4] === "" ? "result" : $matches[4];
                $actions = [
                    new FourArithmeticOperations($value1, $operator, $value2, $assign),
                    new AddVariable($assign, "{".$assign."}", Variable::STRING, false),
                ];
                break;
            case ProcessIds::ADD_VARIABLE:
                $data = explode(";", $content);
                $helper = MineflowMain::getVariableHelper();
                $value = $helper->currentType($data[1]);
                $actions = [new AddVariable($data[0], $value, $helper->getType($data[1]), false)];
                break;
            case ProcessIds::SET_SCALE:
                $actions = [new SetScale("target", $content)];
                break;
            case ProcessIds::EVENT_CANCEL:
                $actions = [new EventCancel()];
                break;
            case ProcessIds::ADD_MONEY:
                $actions = [new AddMoney("{target.name}", $content)];
                break;
            case ProcessIds::TAKE_MONEY:
                $actions = [new TakeMoney("{target.name}", $content)];
                break;
            case ProcessIds::COOPERATION:
                $actions = [new ExecuteRecipe("chain/".$content)];
                break;
            case ProcessIds::DELETE_VARIABLE:
                $actions = [new DeleteVariable($content, false)];
                break;
            case ProcessIds::SET_BLOCKS:
                $data = $this->parseIFSetBlocks($content);
                if ($data[0] === $data[1]) {
                    $position = $this->parseIfPosition($data[0]);
                    if (count($position) === 1) {
                        $actions = [
                            new CreateBlockVariable($data[3]),
                            new SetBlock("{createPosition(".$position[0].")}")
                        ];
                    } else {
                        $actions = [
                            new CreatePositionVariable($position[0], $position[1], $position[2], $data[2]),
                            new CreateBlockVariable($data[3]),
                            new SetBlock(),
                        ];
                    }
                } else {
                    $pos1 = $this->parseIfPosition($data[0]);
                    $pos2 = $this->parseIfPosition($data[1]);
                    if (!is_numeric($pos1[0]) or !is_numeric($pos1[1]) or !is_numeric($pos1[2]) or !is_numeric($pos2[0]) or !is_numeric($pos2[1]) or !is_numeric($pos2[2])) {
                        $this->getLogger()->error("変換に失敗しました: ".$id.", ".$content);
                        return [];
                    }
                    $actions = $this->getPositionRepeat($data[0], $data[1], [
                        new CreatePositionVariable("{x}", "{y}", "{z}", $data[2]),
                        new CreateBlockVariable($data[3]),
                        new SetBlock(),
                    ]);
                }
                break;
            case ProcessIds::COOPERATION_REPEAT:
                $data = explode(";", $content);
                $repeat = new RepeatAction();
                $repeat->setRepeatCount($data[1]);
                $repeat->addAction(new ExecuteRecipe("chain/".$data[0]));
                $actions = [$repeat];
                break;
            case ProcessIds::EXECUTE_OTHER_PLAYER:
                $data = explode(";", $content);
                $actions = [
                    new GetPlayerByName($data[1]),
                    new ExecuteRecipeWithEntity("chain/".$data[0], "player"),
                ];
                break;
            case ProcessIds::DELAYED_COMMAND_CONSOLE:
                $this->addDelayedCommandConsole();
                $actions = [new ExecuteRecipe("aieuo/functions/delayedCommandConsole", $content)];
                break;
            case ProcessIds::SEND_FORM:
                $actions = [new SendForm("target", $content)];
                break;
            case ProcessIds::CLEAR_INVENTORY:
                $actions = [new ClearInventory("target")];
                break;
            case ProcessIds::DELAYED_COOPERATION:
                $this->addDelayedExecute();
                $actions = [new ExecuteRecipe("aieuo/functions/delayedExecute", str_replace("[name]", ",chain/", $content))];
                break;
            case ProcessIds::SHOW_BOSSBAR:
                $titles = explode("[max]", $content);
                $title = $titles[0];
                $values = explode("[value]", $titles[1]);
                $max = $values[0];
                $ids = explode("[id]", $values[1]);
                $value = $ids[0];
                $id = $ids[1];
                $actions = [new ShowBossbar("target", $title, $max, $value, $id)];
                break;
            case ProcessIds::REMOVE_BOSSBAR:
                $actions = [new RemoveBossbar("target", $content)];
                break;
            case ProcessIds::GENERATE_RANDOM_NUMBER:
                $data = explode("[max]", $content);
                $min = $data[0];
                $max = explode("[result]", $data[1])[0];
                $result = explode("[result]", $data[1])[1];
                $actions = [
                    new GenerateRandomNumber($min, $max, $result),
                    new AddVariable($result, "{".$result."}", Variable::NUMBER, false)
                ];
                break;
            case ProcessIds::SET_FOOD:
                $actions = [new SetFood("target", $content)];
                break;
            case ProcessIds::ADD_PARTICLE:
                $data = $this->parseIFParticle($content);
                if (empty($data)) return [];
                $position = $this->parseIfPosition($data[0]);
                if (count($position) === 1) {
                    $actions = [new AddParticle("{createPosition(".$position[0].")}", $data[1], $data[2])];
                } else {
                    $actions = [
                        new CreatePositionVariable($position[0], $position[1], $position[2], $position[3]),
                        new AddParticle("pos", $data[1], $data[2]),
                    ];
                }
                break;
            case ProcessIds::ADD_SOUND:
                $actions = [new PlaySound("target", $content)];
                break;
            case ProcessIds::CHANGE_ITEM_DATA:
                $ids = explode("[:]", $content);
                $damage = $ids[0] === "" ? null : $ids[0];
                $count = (!isset($ids[1]) or $ids[1] === "") ? null : $ids[1];
                $name = (!isset($ids[2]) or $ids[2] === "") ? null : $ids[2];
                $lore = (!isset($ids[3]) or $ids[3] === "") ? null : $ids[3];
                $enchant = (!isset($ids[4]) or $ids[4] === "") ? null : $ids[4];
                if ($enchant!== null) {
                    $this->getLogger()->error("変換に失敗しました (フォーマットが正しくありません): ".$id.", ".$content);
                    return [];
                }
                $actions = [new GetVariableNested("target.hand", "item")];
                if ($damage !== null) $actions[] = new SetItemDamage("item", $damage);
                if ($count !== null) $actions[] = new SetItemCount("item", $count);
                if ($name !== null) $actions[] = new SetItemName("item", $name);
                if ($lore !== null) $actions[] = new SetItemLore("item", $lore);
                $actions[] = new SetItemInHand("target", "item");
                break;
            case ProcessIds::ADD_PARTICLE_RANGE:
                $data = $this->parseIFParticleRange($content);
                if (empty($data)) return [];
                if ($data[0] === $data[1]) {
                    $position = $this->parseIfPosition($data[0]);
                    if (count($position) === 1) {
                        $actions = [new AddParticle("{createPosition(".$position[0].")}", $data[1])];
                    } else {
                        $actions = [
                            new CreatePositionVariable($position[0], $position[1], $position[2], $position[3]),
                            new AddParticle("pos", $data[1]),
                        ];
                    }
                } else {
                    $pos1 = $this->parseIfPosition($data[0]);
                    $pos2 = $this->parseIfPosition($data[1]);
                    if (!is_numeric($pos1[0]) or !is_numeric($pos1[1]) or !is_numeric($pos1[2]) or !is_numeric($pos2[0]) or !is_numeric($pos2[1]) or !is_numeric($pos2[2])) {
                        $this->getLogger()->error("変換に失敗しました: ".$id.", ".$content);
                        return [];
                    }
                    $actions = $this->getPositionRepeat($data[0], $data[1], [
                        new CreatePositionVariable("{x}", "{y}", "{z}", $pos1[3]),
                        new AddParticle("pos", $data[2])
                    ]);
                }
                break;
            case ProcessIds::GET_INVENTORY_CONTENTS:
                $actions = [new GetInventoryContents("target", $content)];
                break;
            default:
                $this->getLogger()->error("変換に失敗しました (不明なID): ".$id);
                return [];
        }
        return $actions;
    }

    /**
     * @param int $id
     * @param string $content
     * @return Condition[]
     */
    private function convertCondition(int $id, string $content): array {
        $content = $this->convertContent($content);
        switch ($id) {
            case ConditionIds::TAKEMONEY:
                $conditions = [new TakeMoneyCondition("{target.name}", $content)];
                break;
            case ConditionIds::IN_HAND:
            case ConditionIds::EXISTS_ITEM:
            case ConditionIds::REMOVE_ITEM:
            case ConditionIds::CAN_ADD_ITEM:
                $item = $this->parseIfItem($content);
                $itemStr = empty($item[2]) ? "{createItem(".$item[0].",".$item[1].")}" : "{createItem(".$item[0].",".$item[1].",".$item[2].")}";
                if (!empty($item[3])) {
                    $itemStr = "{setLore(".$itemStr.", ".$item[3].")}";
                }
                if (!empty($item[4])) {
                    foreach (explode(";", $item[4]) as $enchant1) {
                        $enchants = explode(",", trim($enchant1));
                        $itemStr = "{addEnchant(".$itemStr.", ".trim($enchants[0]).", ".(trim($enchants[1] ?? "1")).")}";
                    }
                }
                switch ($id) {
                    case ConditionIds::IN_HAND:
                        $conditions = [new InHand("target", $itemStr)];
                        break;
                    case ConditionIds::EXISTS_ITEM:
                        $conditions = [new ExistsItem("target", $itemStr)];
                        break;
                    case ConditionIds::REMOVE_ITEM:
                        $conditions = [new RemoveItemCondition("target", $itemStr)];
                        break;
                    case ConditionIds::CAN_ADD_ITEM:
                        $conditions = [new CanAddItem("target", $itemStr)];
                        break;
                    default:
                        return [];
                }
                break;
            case ConditionIds::IS_SNEAKING:
                $conditions = [new IsSneaking()];
                break;
            case ConditionIds::OVERMONEY:
                $conditions = [new OverMoney("{target.name}", $content)];
                break;
            case ConditionIds::GAMEMODE:
                $conditions = [new Gamemode("target", (int)$content)];
                break;
            case ConditionIds::NO_CHECK:
                $conditions = [new CheckNothing()];
                break;
            case ConditionIds::COMPARISON:
                if (!preg_match("/(.*)\[ope:([0-9])](.*)/", $content, $matches)) {
                    $this->getLogger()->error("変換に失敗しました (フォーマットが正しくありません): ".$id.", ".$content);
                    return [];
                }
                $operator = (int)$matches[2];
                $value1 = trim(rtrim($matches[1]));
                $value2 = trim(rtrim($matches[3]));
                if (is_numeric($value1) and is_numeric($value2) and $operator <= 5) {
                    $conditions = [new ComparisonNumber($value1, $operator, $value2)];
                } else {
                    switch ($operator) {
                        case Comparison::EQUAL:
                            $newOperator = ComparisonString::EQUALS;
                            break;
                        case Comparison::NOT_EQUAL:
                            $newOperator = ComparisonString::NOT_EQUALS;
                            break;
                        case Comparison::CONTAINS:
                            $newOperator = ComparisonString::CONTAINS;
                            break;
                        case Comparison::NOT_CONTAINS:
                            $newOperator = ComparisonString::NOT_CONTAINS;
                            break;
                        case Comparison::GREATER:
                        case Comparison::LESS:
                        case Comparison::GREATER_EQUAL:
                        case Comparison::LESS_EQUAL:
                            $conditions = [new ComparisonNumber($value1, $operator, $value2)];
                            break 2;
                        default:
                            $this->getLogger()->error("変換に失敗しました (不明な比較演算子です): ".$content.", ".$operator);
                            return [];
                    }
                    $conditions = [new ComparisonString($value1, $newOperator, $value2)];
                }
                break;
            case ConditionIds::IS_OP:
                $conditions = [new IsOp()];
                break;
            case ConditionIds::IS_FLYING:
                $conditions = [new IsFlying()];
                break;
            case ConditionIds::RANDOM_NUMBER:
                $data = explode(",", $content);
                $conditions = [new RandomNumber($data[0], $data[1], $data[2])];
                break;
            case ConditionIds::EXISTS_VARIABLE:
                $conditions = [new ExistsVariable($content)];
                break;
            case ConditionIds::LESSMONEY:
                $conditions = [new LessMoney("{target.name}", $content)];
                break;
            case ConditionIds::IN_AREA_AXIS:
                $data = explode("[min]", $content);
                $type = ["x", "y", "z"][(int)$data[0]];
                $data = explode("[max]", $data[1]);
                $min = $data[0];
                $max = $data[1];
                $conditions = [
                    new ComparisonNumber($min, ComparisonNumber::LESS_EQUAL, "{target.$type}"),
                    new ComparisonNumber("{target.$type}", ComparisonNumber::LESS_EQUAL, $max),
                ];
                break;
            default:
                $this->getLogger()->error("変換に失敗しました (不明なID): ".$id);
                return [];
        }
        return $conditions;
    }

    private function convertContent(string $content): string {
        $replaceVariables = [
            "{target}" => "{damaged}",
            "{target_name}" => "{damaged.name}",
            "{target_x}" => "{damaged.x}",
            "{target_y}" => "{damaged.y}",
            "{target_z}" => "{damaged.z}",
            "{target_level}" => "{damaged.level}",
            "{player}" => "{target}",
            "{player_name}" => "{target.name}",
            "{player_pos}" => "{target.position}",
            "{nametag}" => "{target.nameTag}",
            "{player_x}" => "{target.x}",
            "{player_y}" => "{target.y}",
            "{player_z}" => "{target.z}",
            "{player_level}" => "{target.level}",
            "{health}" => "{target.health}",
            "{max_health}" => "{target.maxHealth}",
            "{hand_item}" => "{target.hand}",
            "{hand_name}" => "{target.hand.name}",
            "{hand_id}" => "{target.hand.id}",
            "{hand_damage}" => "{target.hand.damage}",
            "{hand_count}" => "{target.hand.count}",
            "{hand_lore}" => "{target.hand.lore}",
            "{block_name}" => "{block.name}",
            "{block_id}" => "{block.id}",
            "{block_ids}" => "{block.id}:{block.damage}",
            "{block_damage}" => "{block.damage}",
            "{block_x}" => "{block.x}",
            "{block_y}" => "{block.y}",
            "{block_z}" => "{block.z}",
            "{block_level}" => "{block.level}",
            "{block_pos}" => "{block.position}",
            "{item_name}" => "{target.item.name}",
            "{item_id}" => "{item.id}",
            "{item_damage}" => "{item.damage}",
            "{item_count}" => "{item.count}",
            "{item_lore}" => "{item.lore}",
            "{input_name}" => "{inputs}",
            "{input_id}" => "{inputs}",
            "{output_name}" => "{outputs}",
            "{output_id}" => "{outputs}",
            "{event_damage}" => "{damage}",
            "{event_cause}" => "{cause}",
            "{attacker}" => "{damager}",
            "{attacker_name}" => "{damager.name}",
            "{attacker_x}" => "{damager.x}",
            "{attacker_y}" => "{damager.y}",
            "{attacker_z}" => "{damager.z}",
            "{attacker_level}" => "{damager.level}",
            "{form_data}" => "{form.data}",
            "{form_button}" => "{form.button}",
            "{form_dropdown}" => "{form.selected}",
        ];
        $content = str_replace(array_keys($replaceVariables), array_values($replaceVariables), $content);
        $content = preg_replace("/{(.+)}\[([0-9]+)]/", '{$1[$2]}', $content);
        return $content;
    }

    private function getPositionRepeat(string $pos1, string $pos2, array $actions): array {
        $pos1 = $this->parseIfPosition($pos1);
        $pos2 = $this->parseIfPosition($pos2);

        $result = [
            new AddVariable("x", min($pos1[0], $pos2[0]), Variable::NUMBER),
            new AddVariable("y", min($pos1[1], $pos2[1]), Variable::NUMBER),
            new AddVariable("z", min($pos1[2], $pos2[2]), Variable::NUMBER),
        ];

        $axises = ["x", "y", "z"];
        /** @var RepeatAction $repeats */
        $action = null;
        /** @var RepeatAction $prev */
        $prev = null;
        for ($i=0; $i<3; $i++) {
            if ($pos1[$i] !== $pos2[$i]) {
                $diff = max($pos1[$i], $pos2[$i]) - min($pos1[$i], $pos2[$i]) + 1;
                $repeat = (new RepeatAction([], $diff))->setStartIndex((string)min($pos1[$i], $pos2[$i]))->setCounterName($axises[$i]);
                if ($action === null) $action = $repeat;
                else $prev->addAction($repeat);
                $prev = $repeat;
            }
        }

        if (isset($repeat)) {
            foreach ($actions as $item) {
                $repeat->addAction($item);
            }
        }

        $result[] = $action;
        return $result;
    }

    private function parseIfItem(string $content): array {
        $data = explode(":", $content);
        $id = $data[0].":".$data[1];
        $count = $data[2];
        $name = $data[3] ?? "";
        $lore = $data[4] ?? "";
        $enchant = $data[5] ?? "";
        return [$id, $count, $name, $lore, $enchant];
    }

    private function parseIfPosition(string $content): array {
        $data = explode(",", $content);
        if (!isset($data[3])) {
            $this->getLogger()->warning("ワールド名がありません. 正しく動作しない可能性があります: ".$content);
        }
        if (!isset($data[1])) {
            $this->getLogger()->warning("座標が不足しています. 正しく動作しない可能性があります: ".$content);
            return [$content];
        }
        return [$data[0], $data[1], $data[2], $data[3] ?? "world"];
    }

    private function parseIFSetBlocks(string $content): array {
        return explode(";", $content);
    }

    private function parseIFParticle(string $content): array {
        $positions = explode("[particle]", $content);
        if (!isset($positions[1])) {
            $this->getLogger()->error("変換に失敗しました (パーティクルのフォーマットが正しくありません): ".$content);
            return [];
        }
        $position = $positions[0];
        $particles = explode("[amount]", $positions[1]);
        $particle = $particles[0];
        $amount = $particles[1] ?? 1;
        return [$position, $particle, (int)$amount];
    }

    private function parseIFParticleRange(string $content): array {
        $positions = explode("[position2]", $content);
        if (!isset($positions[1])) {
            $this->getLogger()->error("変換に失敗しました (範囲パーティクルのフォーマットが正しくありません1): ".$content);
            return [];
        }
        $position1 = $positions[0];
        $particles = explode("[particle]", $positions[1]);
        if (!isset($particles[1])) {
            $this->getLogger()->error("変換に失敗しました (範囲パーティクルのフォーマットが正しくありません2): ".$content);
            return [];
        }
        $position2 = $particles[0];
        $particle = $particles[1];
        return [$position1, $position2, $particle];
    }

    private function addDelayedExecute() {
        $recipe = new Recipe("delayedExecute", "aieuo/functions", "aieuooo");
        $recipe->setArguments(["time", "name"]);
        $recipe->addAction(new Wait("{time}"));
        $recipe->addAction(new ExecuteRecipe("{name}"));
        $recipe->save($this->recipeDir);
    }

    private function addDelayedCommandConsole() {
        $recipe = new Recipe("delayedCommandConsole", "aieuo/functions", "aieuooo");
        $recipe->setArguments(["time", "command"]);
        $recipe->addAction(new Wait("{time}"));
        $recipe->addAction(new CommandConsole("{command}"));
        $recipe->save($this->recipeDir);
    }

    private function addDelayedCommand() {
        $recipe = new Recipe("delayedCommand", "aieuo/functions", "aieuooo");
        $recipe->setArguments(["time", "command", "player"]);
        $recipe->addAction(new Wait("{time}"));
        $recipe->addAction(new Command("player", "{command}"));
        $recipe->save($this->recipeDir);
    }
}