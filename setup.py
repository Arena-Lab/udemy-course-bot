"""
Setup script for Udemy Course Bot
"""
import os
import sys
import subprocess
from pathlib import Path

def check_python_version():
    """Check if Python version is compatible"""
    if sys.version_info < (3, 9):
        print("❌ Python 3.9 or higher is required")
        print(f"Current version: {sys.version}")
        return False
    
    print(f"✅ Python version: {sys.version.split()[0]}")
    return True

def install_dependencies():
    """Install required dependencies"""
    print("📦 Installing dependencies...")
    
    try:
        subprocess.check_call([sys.executable, "-m", "pip", "install", "-r", "requirements.txt"])
        print("✅ Dependencies installed successfully")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        return False

def create_directories():
    """Create necessary directories"""
    directories = ["logs", "config", "utils", "scrapers"]
    
    for directory in directories:
        Path(directory).mkdir(exist_ok=True)
    
    print("✅ Directories created")

def check_env_file():
    """Check if .env file exists and is configured"""
    env_file = Path(".env")
    
    if not env_file.exists():
        print("❌ .env file not found")
        print("Please copy .env.example to .env and configure your settings")
        return False
    
    # Check for required settings
    required_settings = [
        "TELEGRAM_BOT_TOKEN",
        "TELEGRAM_CHANNEL_ID", 
        "TELEGRAM_ADMIN_ID"
    ]
    
    with open(env_file, 'r') as f:
        content = f.read()
    
    missing_settings = []
    for setting in required_settings:
        if f"{setting}=your_" in content or f"{setting}=" not in content:
            missing_settings.append(setting)
    
    if missing_settings:
        print("⚠️  Please configure these settings in .env:")
        for setting in missing_settings:
            print(f"   - {setting}")
        return False
    
    print("✅ .env file configured")
    return True

def run_tests():
    """Run basic tests to verify setup"""
    print("🧪 Running basic tests...")
    
    try:
        # Test imports
        from config.settings import Config
        from utils import CourseDatabase, MessageFormatter, ScraperManager
        
        # Test configuration
        Config.validate_config()
        
        # Test database
        db = CourseDatabase()
        
        print("✅ All tests passed")
        return True
        
    except Exception as e:
        print(f"❌ Test failed: {e}")
        return False

def main():
    """Main setup function"""
    print("🚀 Setting up Udemy Course Bot...")
    print("=" * 50)
    
    # Check Python version
    if not check_python_version():
        return False
    
    # Create directories
    create_directories()
    
    # Install dependencies
    if not install_dependencies():
        return False
    
    # Check .env configuration
    if not check_env_file():
        print("\n📝 Next steps:")
        print("1. Configure your .env file with proper credentials")
        print("2. Run 'python setup.py' again to verify setup")
        print("3. Run 'python main.py' to start the bot")
        return False
    
    # Run tests
    if not run_tests():
        return False
    
    print("\n" + "=" * 50)
    print("🎉 Setup completed successfully!")
    print("\n📝 Next steps:")
    print("1. Run 'python main.py' to start the bot")
    print("2. Send /start to your bot to verify it's working")
    print("3. Use /test command to test all scrapers")
    print("\n📚 For help, check README.md")
    
    return True

if __name__ == "__main__":
    success = main()
    sys.exit(0 if success else 1)
