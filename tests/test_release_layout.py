"""Validate installer identity and simulate replacing an existing plugin."""
import importlib.util
from pathlib import Path
import shutil
import tempfile
import unittest
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('utr_build', ROOT / 'tools/build-release.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class ReleaseLayoutTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix='utr-release-test-')
        self.root = Path(self.temporary.name)
        shutil.copytree(ROOT / 'utm-traffic-recorder', self.root / 'utm-traffic-recorder')
        shutil.copytree(ROOT / 'integrations', self.root / 'integrations')

    def tearDown(self):
        self.temporary.cleanup()

    def test_overwrites_same_entry_point_without_creating_a_second_plugin(self):
        archive = builder.build_release(self.root)
        plugins = self.root / 'site/wp-content/plugins'
        installed = plugins / builder.PLUGIN_MAIN
        installed.parent.mkdir(parents=True)
        installed.write_text('<?php\n/* Plugin Name: UTM Traffic Recorder\nVersion: 2.6.11 */')
        with zipfile.ZipFile(archive) as package:
            self.assertIn(builder.PLUGIN_MAIN, package.namelist())
            self.assertEqual({name.split('/')[0] for name in package.namelist()}, {builder.PLUGIN_SLUG})
            self.assertTrue(all('\\' not in name for name in package.namelist()))
            package.extractall(plugins)
        self.assertEqual([p.name for p in plugins.iterdir()], [builder.PLUGIN_SLUG])
        self.assertEqual(installed.read_bytes(), (ROOT / builder.PLUGIN_MAIN).read_bytes())
        self.assertTrue((self.root / 'dist/utm-traffic-recorder.zip').is_file())
        self.assertFalse((self.root / 'utm-traffic-recorder.zip').exists())

    def test_rejects_nested_folder_and_extra_plugin_header(self):
        nested = self.root / 'utm-traffic-recorder/utm-traffic-recorder'
        nested.mkdir()
        (nested / 'other.php').write_text('<?php')
        with self.assertRaisesRegex(ValueError, 'Nested plugin root'):
            builder.build_release(self.root)
        (nested / 'other.php').unlink()
        nested.rmdir()
        (self.root / 'utm-traffic-recorder/extra.php').write_text('<?php\n/*\n * Plugin Name: Unexpected duplicate\n */')
        with self.assertRaisesRegex(ValueError, 'Multiple plugin entry'):
            builder.build_release(self.root)

    def test_rejects_private_runtime_files_and_version_mismatch(self):
        sensitive = self.root / 'utm-traffic-recorder/.env'
        sensitive.write_text('example=not-a-real-secret')
        with self.assertRaisesRegex(ValueError, 'Unexpected runtime/private'):
            builder.build_release(self.root)
        sensitive.unlink()
        readme = self.root / 'utm-traffic-recorder/readme.txt'
        readme.write_text('Stable tag: 0.0.0')
        with self.assertRaisesRegex(ValueError, 'Stable tag does not match'):
            builder.build_release(self.root)


if __name__ == '__main__':
    unittest.main()
