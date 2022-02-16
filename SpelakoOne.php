<?php
/*
 * Copyright (C) 2020-2022 Spelako Project
 * 
 * This file is part of SpelakoOne.
 * Permission is granted to use, modify and/or distribute this program 
 * under the terms of the GNU Affero General Public License version 3.
 * You should have received a copy of the license along with this program.
 * If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 * 此文件是 SpelakoOne 的一部分.
 * 在 GNU Affero 通用公共许可证第三版的约束下,
 * 你有权使用, 修改, 复制和/或传播该软件.
 * 你理当随同本程序获得了此许可证的副本.
 * 如果没有, 请查阅 <https://www.gnu.org/licenses/agpl-3.0.html>.
 * 
 */

$localPort = 5701;

function _log($msg) {
	echo('['.date('Y-m-d H:m:s').'] '.$msg.PHP_EOL);
}

$cliargs = getopt('', ['core:', 'config:', 'host:']);

if(!(isset($cliargs['core']) && file_exists($cliargs['core']))) {
	exit('提供的 SpelakoCore 路径无效. 请使用命令行参数 "--core" 指向正确的 SpelakoCore.php.');
}

if(!(isset($cliargs['config']) && file_exists($cliargs['config']))) {
	exit('提供的配置文件路径无效. 请使用命令行参数 "--config" 指向正确的 config.json.');
}

if(empty($cliargs['host'])) {
	exit('未指定 host. 请使用命令行参数 "--host" 指定正确的值.');
}

$socket = stream_socket_server('tcp://0.0.0.0:'.$localPort);
if($socket == false) exit('无法在本地启动端口为 '.$localPort.' 的事件监听 HTTP 服务器.');

require_once(realpath($cliargs['core']));
$core = new SpelakoCore(realpath($cliargs['config']));

echo SpelakoUtils::buildString([
	'Copyright (C) 2020-2022 Spelako Project',
	'This program is licensed under the GNU Affero General Public License version 3 (AGPLv3).'
]).PHP_EOL;

_log('成功在本地启动端口为 '.$localPort.' 的事件监听 HTTP 服务器. 开始监听请求...');

while(true) {
	if($conn = @stream_socket_accept($socket, -1)) {
		$datagram = fread($conn, 1024); // 请求头部长度应该不会超过 1024
		$header_length = strpos($datagram, "\r\n\r\n") + 4;
		$content_length = intval(substr($datagram, strpos($datagram, 'Content-Length: ') + 16));
		if($header_length + $content_length > 1024) $datagram .= fread($conn, $header_length + $content_length - strlen($datagram));

		fwrite($conn, "HTTP/1.1 204 No Content\r\n\r\n");
		fclose($conn);

		$content = json_decode(substr($datagram, $header_length));
		if($content->post_type == 'message' && $content->message_type == 'group' && $content->message[0] == '/') {
			_log(SpelakoUtils::buildString(
				'群: %1$s | 用户: %2$s (%3$s) | 消息: %4$s',
				[
					$content->group_id,
					$content->sender->nickname,
					$content->user_id,
					$content->message
				]
			));
			$requestResult = $core->execute($content->message, $content->user_id);
			if(!$requestResult) {
				$cmd = explode(' ', $content->message)[0];
				foreach($core->getCommands() as $pointer) foreach($pointer->getName() as $pointerCmd) {
					similar_text($cmd, $pointerCmd, $percent);
					if($percent > 70) {
						$requestResult = SpelakoUtils::buildString([
							'你可能想输入此命令: %1$s',
							'但是你输入的是: %2$s'
						], [
							$pointerCmd,
							$cmd
						]);
						break 2;
					}
				}
			}
			file_get_contents($cliargs['host'].'/send_group_msg?'.http_build_query([
				'group_id' => $content->group_id,
				'message' => $requestResult
			]));
		}
	}
}
?>