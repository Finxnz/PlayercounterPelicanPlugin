<?php

namespace Finxnz\PlayerCounter\Models;

use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Server;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $query_type
 * @property ?int $query_port_offset
 * @property Collection|Egg[] $eggs
 * @property int|null $eggs_count
 * @property Collection|Server[] $servers
 * @property int|null $servers_count
 */
class GameQuery extends Model
{
    protected $fillable = [
        'query_type',
        'query_port_offset',
    ];

    protected $attributes = [
        'query_port_offset' => null,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $gameQuery) {
            $gameQuery->query_type = mb_strtolower($gameQuery->query_type);
        });
    }

    public function eggs(): BelongsToMany
    {
        return $this->belongsToMany(Egg::class)->using(EggGameQuery::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_game_query', 'game_query_id', 'server_id', 'id', 'uuid')
            ->using(ServerGameQuery::class);
    }

    /** @return array<string, mixed> */
    public function runQuery(Allocation $allocation): array
    {
        $ip = is_ipv6($allocation->ip) ? '[' . $allocation->ip . ']' : $allocation->ip;
        $port = $allocation->port + ($this->query_port_offset ?? 0);

        try {
            if ($this->query_type === 'minecraft') {
                return $this->queryMinecraft($ip, $port);
            }
            
            return ['query_error' => true];
        } catch (Exception $exception) {
            try {
                report($exception);
            } catch (Exception $reportException) {
            }
        }

        return ['query_error' => true];
    }

    /** @return array<string, mixed> */
    private function queryMinecraft(string $ip, int $port): array
    {
        $socket = @fsockopen('udp://' . $ip, $port, $errno, $errstr, 3);
        
        if (!$socket) {
            return ['query_error' => true];
        }

        stream_set_timeout($socket, 3);
        stream_set_blocking($socket, true);

        try {
            $handshake = pack('c*', 0xFE, 0xFD, 0x09, 0x00, 0x00, 0x00, 0x00);
            fwrite($socket, $handshake);
            
            $response = fread($socket, 2048);
            if (!$response || strlen($response) < 5) {
                fclose($socket);
                return ['query_error' => true];
            }

            $challengeToken = substr($response, 5);
            $challengeToken = rtrim($challengeToken, "\x00");
            
            if (!is_numeric($challengeToken)) {
                fclose($socket);
                return ['query_error' => true];
            }

            $token = pack('N', intval($challengeToken));
            $statRequest = pack('c*', 0xFE, 0xFD, 0x00) . pack('N', 0) . $token . pack('c*', 0x00, 0x00, 0x00, 0x00);
            fwrite($socket, $statRequest);

            $data = '';
            while (!feof($socket)) {
                $chunk = fread($socket, 2048);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
                if (strlen($data) > 8192) {
                    break;
                }
            }

            fclose($socket);

            if (strlen($data) < 16) {
                return ['query_error' => true];
            }

            return $this->parseMinecraftQuery($data);
        } catch (Exception $e) {
            @fclose($socket);
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function parseMinecraftQuery(string $data): array
    {
        try {
            $data = substr($data, 16);
            
            $parts = explode("\x00\x00\x01player_\x00\x00", $data);
            
            if (count($parts) < 2) {
                return ['query_error' => true];
            }

            $kvSection = $parts[0];
            $kvPairs = explode("\x00", $kvSection);
            
            $result = [];
            for ($i = 0; $i < count($kvPairs) - 1; $i += 2) {
                $key = $kvPairs[$i];
                $value = $kvPairs[$i + 1] ?? '';
                
                if ($key !== '') {
                    $result[$key] = $value;
                }
            }

            $playerSection = $parts[1];
            $playerNames = array_filter(explode("\x00", $playerSection), function($name) {
                return $name !== '';
            });

            $players = [];
            foreach ($playerNames as $name) {
                $players[] = [
                    'player' => $name,
                    'time' => 0,
                ];
            }

            return [
                'gq_hostname' => $result['hostname'] ?? 'Unknown',
                'gq_numplayers' => isset($result['numplayers']) ? (int)$result['numplayers'] : count($players),
                'gq_maxplayers' => isset($result['maxplayers']) ? (int)$result['maxplayers'] : 20,
                'gq_mapname' => $result['map'] ?? 'world',
                'players' => $players,
            ];
        } catch (Exception $e) {
            return ['query_error' => true];
        }
    }
}