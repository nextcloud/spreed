<?php
/**
 * @copyright 2018, Denis Mosolov <denismosolov@gmail.com>
 *
 * @author Denis Mosolov <denismosolov@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Afferoq General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Talk\Tests\php\Command\Stun;

use OCA\Talk\Command\Stun\Delete;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Test\TestCase;

class DeleteTest extends TestCase {
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;

	/** @var Delete|\PHPUnit_Framework_MockObject_MockObject */
	private $command;

	/** @var InputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $input;

	/** @var OutputInterface|\PHPUnit_Framework_MockObject_MockObject */
	private $output;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);

		$this->command = new Delete($this->config);

		$this->input = $this->createMock(InputInterface::class);
		$this->output = $this->createMock(OutputInterface::class);
	}

	public function testAddDefaultServerIfEmpty() {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun1.test.com:443');
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn('');
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('stun_servers'),
				$this->equalTo(json_encode(['stun.nextcloud.com:443']))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>You deleted all STUN servers. A default STUN server was added.</info>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testDelete() {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun1.test.com:443');
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn(json_encode(['stun1.test.com:443', 'stun2.test.com:443']));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('stun_servers'),
				$this->equalTo(json_encode(['stun2.test.com:443']))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>Deleted stun1.test.com:443.</info>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}

	public function testNothingToDelete() {
		$this->input->method('getArgument')
			->with('server')
			->willReturn('stun3.test.com:443');
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'stun_servers')
			->willReturn(json_encode(['stun1.test.com:443']));
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				$this->equalTo('spreed'),
				$this->equalTo('stun_servers'),
				$this->equalTo(json_encode(['stun1.test.com:443']))
			);
		$this->output->expects($this->once())
			->method('writeln')
			->with($this->equalTo('<info>There is nothing to delete.</info>'));

		$this->invokePrivate($this->command, 'execute', [$this->input, $this->output]);
	}
}
