<?php

namespace HimmelKreis4865\BetterXray;

use Exception;
use pocketmine\block\Block;
use pocketmine\level\format\Chunk;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelChunkPacket;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\scheduler\AsyncTask;
use function array_rand;
use function mt_rand;

class ChunkModificationTask extends AsyncTask {
	/** @var Chunk $chunk */
	public $chunk;
	
	/** @var string $player */
	public $player;
	
	/** @var int $level */
	public $level;
	
	/** @var int[] */
	public const BLOCK_SIDES = [
		Vector3::SIDE_DOWN,
		Vector3::SIDE_UP,
		Vector3::SIDE_NORTH,
		Vector3::SIDE_SOUTH,
		Vector3::SIDE_WEST,
		Vector3::SIDE_EAST
	];
	
	
	/**
	 * ChunkModificationTask constructor.
	 *
	 * @param Chunk $chunk
	 * @param Player $player
	 */
	public function __construct(Chunk $chunk, Player $player) {
		$this->chunk = $chunk->fastSerialize();
		$this->player = $player->getName();
		$this->level = $player->getLevel()->getId();
	}
	
	
	public function onRun() {
		$chunk = Chunk::fastDeserialize($this->chunk);
		
		$ores = [14, 15, 21, 22, 41, 42, 56, 57, 73, 129, 133, 152];
		for ($yy = 0; $yy < 8; ++$yy) {
			$subchunk = $chunk->getSubChunk($yy);
			
			for ($x = 0; $x < 16; ++$x) {
				for ($z = 0; $z < 16; ++$z) {
					for ($y = 0; $y < 16; ++$y) {
						if ((int)$subchunk->getBlockId($x, $y, $z) !== 1 || mt_rand(1, 10) < 8) continue;
						
						$vector = new Vector3(($chunk->getX() * 16) + $x, ($yy * 16) + $y, ($chunk->getZ() * 16) + $z);
						
						foreach (self::BLOCK_SIDES as $side) {
							$side = $vector->getSide($side);
							if ($chunk->getBlockId($side->x & 0x0f, $side->y, $side->z & 0x0f) !== Block::STONE)
								continue 2;
						}
						$subchunk->setBlockId($x, $y, $z, $ores[array_rand($ores)]);
					}
				}
			}
		}
		
		$this->setResult($chunk);
	}
	
	
	public function onCompletion(Server $server) {
		$player = $server->getPlayer($this->player);
		
		if ($player instanceof Player && $player->getLevel()->getId() === $this->level && $this->hasResult()) {
			/** @var Chunk $chunk */
			$chunk = $this->getResult();
			try {
				foreach ($player->getLevelNonNull()->getChunkTiles($chunk->getX(), $chunk->getZ()) as $tile) {
					$chunk->addTile($tile);
				}
			} catch (Exception $exception) {
			
			}
			
			$chunkPacket = LevelChunkPacket::withoutCache($chunk->getX(), $chunk->getZ(), $chunk->getSubChunkSendCount(), $chunk->networkSerialize());
			
			$batchPacket = new BatchPacket();
			$batchPacket->addPacket($chunkPacket);
			$batchPacket->setCompressionLevel(7);
			$batchPacket->encode();
			
			$modifiedChunk = new ModifiedChunk($batchPacket->buffer);
			
			if (strlen($modifiedChunk->buffer) > 0) {
				$modifiedChunk->isEncoded = true;
				$player->sendDataPacket($modifiedChunk);
			}
		}
	}
}