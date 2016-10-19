<?php

namespace onigo;

use pocketmine\Server;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\Effect;
use pocketmine\entity\Entity;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\Level;
use pocketmine\event\Listener;
use pocketmine\scheduler\PluginTask;
use pocketmine\scheduler\CallbackTask;
use pocketmine\item\Item;
use pocketmine\network\protocol\AddEntityPacket;


class main extends PluginBase implements Listener{

    const NETWORK_ID = 93;

    private $status;

    private $players;


       public function onEnable(){

             $this->getServer()->getPluginManager()->registerEvents($this, $this);

             if($this->getServer()->getPluginManager()->getPlugin("ExpLevel") != null){

                     $this->api = $this->getServer()->getPluginManager()->getPlugin("ExpLevel");
                     $this->getLogger()->info("§bExpLevelを読み込みました");
             }else{

                     $this->getLogger()->warning("§cExpLevelが見つかりません");
                     $this->getLogger()->notice("§eサーバーを閉じます");
                     $this->getServer()->shutdown();
             }
             

             $this->status = 0;  //game判定  
             $this->s = 0;     //一回目かどうか
             $this->b = 0;     //
             $this->pc = 0;    //人数
             $this->ss = 0;
             $this->ac = 0;   //start schedulerの判定
       
             $this->onis = [];
             $this->players = [];
       }

       public function load(){

             $this->status = 0;
             $this->s = 2;
             $this->ss = 0;
             $this->pc = 0;
             $this->b = 0;
       
             $this->onis = [];
             $this->players = [];

             $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"CountCheckTask"]), 20 * 10);

       }

       public function check(){

         if($this->b == 0){

             if(count($this->getServer()->getOnlinePlayers()) >= 4){

                      $task = new start($this, 30);
                      $this->b = 1;
                      $this->taskid = $task->getTaskId();
                      $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
                      foreach($this->getServer()->getOnlinePlayers() as $player){

                              $name = $player->getName();

                              array_push($this->players, $name);
                      }
                      $this->ss = 0;
             }else{


                      $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"CountCheckTask"]), 20 * 10);
             }
          }
       }


       public function onJoin(PlayerJoinEvent $ev){

              $player = $ev->getPlayer();
              $name = $player->getName();
              

              $player->setGamemode(0);
              $player->getInventory()->clearAll();

              if($this->status == 0){ //gameが始まっていなかったら

                     if(!in_array($name, $this->players)){ //参加していないかどうか

                             array_push($this->players, $name); //playerの名前を配列に追加
                             ++$this->pc;

                          if($this->s == 0){ //一回目なら
                              if($this->ac == 0){
                                if(count($this->players) >= 4){  //playerが4人以上なら

                                     $task = new start($this, 30);
                                     $this->taskid = $task->getTaskId();
                                     $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);
                                     $this->ac = 1; //起動したことを宣言
                                 }
                               }
                           }
                      }
                }
              
          

              if($this->status == 1){ //gameが始まっていたら

                     $player->setGamemode(3); //スぺクテイエターモードに変更
                     $player->sendMessage("§bInfo§f > §6只今ゲーム中です。観戦しながらお待ちください。");
                     $player->setDisplayName("[§a待機者§f] ".$player->getName());
              }

        }


      public function onTouch(PlayerInteractEvent $ev){

             $player = $ev->getPlayer();
             $name = $player->getName();

             $item = $player->getItemInHand();

             $id = $item->getId();
             $meta = $item->getDamage();

             


             if($this->status == 1){

                    

                    if($id.":".$meta == "289:0"){

                              

                              $item = Item::get(289, 0, 1);

                              $player->getInventory()->removeItem($item); // remove item
                              $effect = Effect::getEffect(14);
                              $effect->setDuration(20 * 3);
                              $effect->setAmplifier(2);
                              $effect->setVisible(false);
                              $player->addEffect($effect); //set effect

                     }

                     if($id.":".$meta == "288:0"){

                              

                              $item = Item::get(288, 0, 1);

                              $player->getInventory()->removeItem($item); // remove item
                              $effect = Effect::getEffect(8);
                              $effect->setDuration(20 * 5);
                              $effect->setAmplifier(4);
                              $effect->setVisible(false);
                              $player->addEffect($effect); //set effect
                      }
              }
      }





       public function start(){
 
             $task = new game($this, 600);
             $this->id = $task->getTaskId();

             $this->getServer()->getScheduler()->scheduleRepeatingTask($task, 20);


             $head = Item::get(314, 0, 1); //ヘルメット
             $chestp = Item::get(315, 0, 1); //チェストプレート
             $regins = Item::get(316, 0, 1); //レギンス
             $boot = Item::get(317, 0, 1);  //ブーツ

             $item1 = Item::get(289, 0, 2); //火薬
             $item2 = Item::get(288, 0, 3); //羽

             foreach($this->getServer()->getOnlinePlayers() as $player){

                    $player->getInventory()->addItem($item1);
                    $player->getInventory()->addItem($item2);
                    $player->setDisplayName("[§b逃走者§f] §b".$player->getName()."§f");
                    $player->setNameTag("[§b逃走者§f] §b".$player->getName());
             }




             $this->status = 1;//game中を宣言
             $this->ss = 2;
             $this->stopt(); //taskを止める

             $this->getServer()->getScheduler()->scheduleDelayedTask(new CallbackTask([$this,"CanMoveTask"]), 20 * 20);

             if($this->pc < 6){
                 $key = array_rand($this->players, 1);
            

                 $this->oname = $this->players[$key];
             }
             if($this->pc >= 6){

                 $keys = array_rand($this->players, 2);
                 
                 $this->oname = [];
                 $this->oname = array($this->players[$keys[0]], $this->players[$keys[1]]);
             }

 
             if(is_array($this->oname)){

                 foreach($this->oname as $oname){

                      array_push($this->onis, $oname);

                      $oni = $this->getServer()->getPlayer($oname);

                      if($oni instanceof Player){
                            $oni->setDataProperty(Entity::DATA_NO_AI, Entity::DATA_TYPE_BYTE, 1);  //鬼を動けなくする

                            $oni->sendMessage("§eNotice §f> §bあなたは鬼になりました。ほかのプレイヤーにタッチして捕まえましょう");
                            $oni->sendMessage("§eNotice §f> §a20秒後に動けます");

                            $oni->getInventory()->clearAll();

                            $oni->getInventory()->setArmorItem(0, $head);
                            $oni->getInventory()->setArmorItem(1, $chestp);
                            $oni->getInventory()->setArmorItem(2, $regins);
                            $oni->getInventory()->setArmorItem(3, $boot);
                            $oni->getInventory()->sendArmorContents($oni); //送信
                            
                            $oni->setDisplayName("[§c鬼§f] §c".$oni->getName()."§f");
                            $oni->setNameTag("[§c鬼§f] §c".$oni->getName());

                       }else{


                             $this->end();
                             $this->getServer()->broadcastMessage("§cWarning§ f> §e鬼が居ないのでゲームを初期化します。");

                       }
                  }
             }else{
 
                 array_push($this->onis, $this->oname);

                 $oni = $this->getServer()->getPlayer($this->oname);

                      if($oni instanceof Player){
                            $oni->setDataProperty(Entity::DATA_NO_AI, Entity::DATA_TYPE_BYTE, 1);  //鬼を動けなくする

                            $oni->sendMessage("§eNotice §f> §bあなたは鬼になりました。ほかのプレイヤーにタッチして捕まえましょう");
                            $oni->sendMessage("§eNotice §f> §a20秒後に動けます");

                            $oni->getInventory()->clearAll();

                            $oni->getInventory()->setArmorItem(0, $head);
                            $oni->getInventory()->setArmorItem(1, $chestp);
                            $oni->getInventory()->setArmorItem(2, $regins);
                            $oni->getInventory()->setArmorItem(3, $boot);
                            $oni->getInventory()->sendArmorContents($oni); //送信
                            $oni->setDisplayName("[§c鬼§f] §c".$oni->getName()."§f");
                            $oni->setNameTag("[§c鬼§f] §c".$oni->getName());

                       }else{


                             $this->end();
                             $this->getServer()->broadcastMessage("§cWarning§ f> §e鬼が居ないのでゲームを初期化します。");

                       }
             }

                 


      }


      public function getSs(){

            return $this->ss;
     }


      public function CanMoveTask(){
 
          foreach($this->onis as $oname){

            $oni = $this->getServer()->getPlayer($oname);

             if($oni instanceof Player){

                  $oni->setDataProperty(Entity::DATA_NO_AI, Entity::DATA_TYPE_BYTE, 0);

                  $oni->sendTip("§b§l GO !!");

             }
          }


     }

     public function CountCheckTask(){

             $this->check();
     }

     public function isOni($name){

          if(in_array($name, $this->onis)){

                 return true;
          }else{

                 return false;
          }
    }


     public function onDamage(EntityDamageEvent $ev){

      if($this->status == 1){

          $entity = $ev->getEntity();
 
          if($ev instanceof EntityDamageByEntityEvent){

                $damager = $ev->getDamager();

                if($entity instanceof Player and $damager instanceof Player){

                     $ename = $entity->getName(); 
                     $dname = $damager->getName();
                   
                     if($this->isOni($dname)){

                         if(!$this->isOni($ename)){

                            $this->getServer()->broadcastMessage("§bInfo§f > §6".$dname."§c が §e".$ename."§c を捕まえました");
                            array_push($this->onis, $ename);


                                $Online = Server::getInstance()->getOnlinePlayers();
				$pk = new AddEntityPacket();
				$pk->type = 93;
				$pk->eid = Entity::$entityCount++;
				$pk->metadata = array();
				$pk->speedX = 0;
				$pk->speedY = 0;
				$pk->speedZ = 0;
 				$pk->yaw = $entity->getYaw();
				$pk->pitch = $entity->getPitch();
				$pk->x = $entity->x;
				$pk->y = $entity->y;
				$pk->z = $entity->z;
				Server::broadcastPacket($Online,$pk);

                                $head = Item::get(314, 0, 1); //ヘルメット
                                $chestp = Item::get(315, 0, 1); //チェストプレート
                                $regins = Item::get(316, 0, 1); //レギンス
                                $boot = Item::get(317, 0, 1);  //ブーツ

                                $entity->getInventory()->clearAll();

                                $entity->getInventory()->setArmorItem(0, $head);
                                $entity->getInventory()->setArmorItem(1, $chestp);
                                $entity->getInventory()->setArmorItem(2, $regins);
                                $entity->getInventory()->setArmorItem(3, $boot);
                                $entity->getInventory()->sendArmorContents($enity); //送信

                                $entity->setDisplayName("[§c鬼§f] §c".$entity->getName()."§f");
                                $entity->setNameTag("[§c鬼§f] §c".$entity->getName());






                            if(count($this->onis) == count($this->players)){

                                   $this->end(1, 1);
                                   $this->ss = 1;
                                   
                            }
                         }else{

                            $damager->sendMessage("§cInfo§f > §eその人は既に鬼です");
                         }
                    }
                }
          }
        }

      $ev->setCancelled(); 
    }


    public function end($stat = 1, $si = null){

         if($stat == 1){

           $this->stoptt();

           
         }
         $level = $this->getServer()->getDefaultLevel();

         $pos = $level->getSafeSpawn();

         $this->getServer()->broadcastMessage("§bInfo§f > §6ゲームが終了しました");

         foreach($this->getServer()->getOnlinePlayers() as $player){

               $player->setDisplayName($player->getName());
               $player->setNameTag($player->getName());
               $player->teleport($pos);

               $player->getInventory()->clearAll();

               if($player->getGamemode() == 3){
 
                     $player->setGamemode(0);
               }
         }

         if(!empty($si)){

                foreach($this->players as $p){

                      if(!$this->isOni($p)){


                          if($this->getServer()->getPlayer($p) instanceof Player){

                              $exp = 20;

                              $bexp = $this->api->getExp($p);

                              $aexp = $exp + $bexp;

                              $this->api->setExp($p,$aexp);

                              
                              $this->getServer()->getPlayer($p)->sendMessage("§bInfo§f > §a逃げ切った報酬として経験値を手に入れました");
                         }
                       }
                }
          }

         $this->load();
    }

    public function stopt(){

           $this->getServer()->getScheduler()->cancelTask($this->taskid);
   }

   public function stoptt(){

           $this->getServer()->getScheduler()->cancelTask($this->id);
   }





   public function onQuit(PlayerQuitEvent $ev){

        $player = $ev->getPlayer();
        $name = $player->getName();

        $player->getInventory()->clearAll();
        
 
        

            if($this->isOni($name)){
 
                  if(($key = array_search($name, $this->onis)) !== false){

                           unset($this->onis[$key]);

                           array_values($this->onis);

                           if($keys = array_search($name, $this->players) !== false){

                                   unset($this->players[$keys]);
                                   array_values($this->players);
                           }
                  }
            }


            if($keys = array_search($name, $this->players) !== false){

                                   unset($this->players[$keys]);
                                   array_values($this->players);
            }

            if($this->status == 1){

               if($this->b == 1){

                if(count($this->players) < 4){

                     $this->end(2);
                     $this->ss = 1;
                 }
                }
            }



            if($player->getGamemode() == 0){

                    --$this->pc;
            }

            $player->setGamemode(0);
        }
   
 

                

}






class start extends PluginTask{


    public function __construct(PluginBase $owner, $count){

           parent::__construct($owner);

           $this->count = $count;

   }


   public function onRun($currentTick){


         $count = --$this->count;


         $ss = $this->getOwner()->getSs();

         if($ss == 1){

                 $this->count = 0;
         }

         if($count > 0){

               $this->getOwner()->getServer()->broadcastPopup("§b".$count."秒");

         }
 
         if($count == 0){


              $this->getOwner()->start(); //ゲームスタート
             
         }

   }

}   



class game extends PluginTask{

      public function __construct(PluginBase $owner, $count){


             parent::__construct($owner);
 
             $this->count = $count;
     }


     public function onRun($currentTick){

            $count = --$this->count;


            $ss = $this->getOwner()->getSs();

            if($ss == 1){

                 $this->count = 0;
            }


            if($count > 10){

                  $this->getOwner()->getServer()->broadcastPopup("§a残り §6".$count." §b秒");
            }

            if($count <= 10){

               if($count > 0){

                 $this->getOwner()->getServer()->broadcastPopup("§a残り §c".$count." §b秒");
               }
            }


            if($count == 0){

                 $this->getOwner()->end(2, 1);
            }
    }

}


       

             

             


