# 🚦 Telegram Rate Limiting Guide

## 📊 **Current Configuration**

Based on Telegram Bot API documentation and best practices:

### **Default Settings (.env)**
```env
TELEGRAM_POST_DELAY=3              # 3 seconds between posts (safe)
TELEGRAM_MAX_POSTS_PER_MINUTE=20   # 20 posts/minute (conservative)
TELEGRAM_BURST_LIMIT=5             # 5 quick posts before longer delay
TELEGRAM_BURST_DELAY=10            # 10 seconds after burst limit
```

### **Telegram Official Limits**
- **30 messages/second** across all chats (global limit)
- **1 message/second** to same chat (recommended)
- **20-25 messages/minute** for channels (safe practice)

## 🎯 **Rate Limiter Features**

### **Smart Rate Limiting**
- ✅ **Per-minute tracking**: Monitors posts in last 60 seconds
- ✅ **Burst protection**: Prevents rapid-fire posting
- ✅ **Minimum delays**: Ensures safe intervals between posts
- ✅ **FloodWait prevention**: Avoids Telegram API errors

### **Dynamic Adjustment**
- ✅ **Runtime changes**: Adjust settings without restart
- ✅ **Admin commands**: `/ratelimit` for live tuning
- ✅ **Statistics**: Real-time monitoring via `/status`

## 📱 **Commands**

### **View Current Settings**
```
/ratelimit
```

### **Adjust Settings**
```
/ratelimit delay 5     # Set 5-second delay between posts
/ratelimit maxpm 15    # Set max 15 posts per minute
/ratelimit burst 3     # Allow 3 quick posts before delay
```

### **Monitor Status**
```
/status                # Shows rate limiter stats
```

## ⚙️ **Recommended Settings**

### **Conservative (Safe)**
```env
TELEGRAM_POST_DELAY=5              # 5 seconds between posts
TELEGRAM_MAX_POSTS_PER_MINUTE=12   # 12 posts/minute
TELEGRAM_BURST_LIMIT=3             # 3 quick posts
TELEGRAM_BURST_DELAY=15            # 15 seconds after burst
```

### **Balanced (Current)**
```env
TELEGRAM_POST_DELAY=3              # 3 seconds between posts
TELEGRAM_MAX_POSTS_PER_MINUTE=20   # 20 posts/minute
TELEGRAM_BURST_LIMIT=5             # 5 quick posts
TELEGRAM_BURST_DELAY=10            # 10 seconds after burst
```

### **Aggressive (Risky)**
```env
TELEGRAM_POST_DELAY=2              # 2 seconds between posts
TELEGRAM_MAX_POSTS_PER_MINUTE=25   # 25 posts/minute
TELEGRAM_BURST_LIMIT=8             # 8 quick posts
TELEGRAM_BURST_DELAY=8             # 8 seconds after burst
```

## 🔍 **How It Works**

### **Rate Limiting Logic**
1. **Check per-minute limit**: If 20+ posts in last minute, wait
2. **Check burst limit**: If 5+ consecutive posts, apply burst delay
3. **Check minimum delay**: Ensure 3+ seconds since last post
4. **Record timestamp**: Track for future calculations

### **FloodWait Prevention**
- **Proactive delays**: Prevents hitting Telegram limits
- **Smart queuing**: Spreads posts over time
- **Error recovery**: Handles rate limit errors gracefully

## 📈 **Performance Impact**

### **With Rate Limiting**
- ✅ **No FloodWait errors**: Prevents API blocks
- ✅ **Consistent posting**: Steady stream of courses
- ✅ **Better reliability**: Reduces failed posts
- ✅ **Channel health**: Avoids spam appearance

### **Timing Examples**
- **3 courses found**: Posted over 6-9 seconds (safe)
- **10 courses found**: Posted over 30-60 seconds (optimal)
- **20 courses found**: Posted over 60-120 seconds (controlled)

## 🛡️ **Safety Features**

### **Built-in Protections**
- **Automatic delays**: Never exceeds safe limits
- **Burst detection**: Prevents rapid posting
- **Error handling**: Continues on rate limit errors
- **Statistics tracking**: Monitor performance

### **Admin Controls**
- **Live adjustment**: Change settings without restart
- **Real-time monitoring**: See current status
- **Emergency controls**: Quickly adjust if needed

## 🎯 **Best Practices**

### **For High Volume**
1. Use **conservative settings** initially
2. Monitor **channel performance**
3. Gradually **increase limits** if stable
4. Watch for **FloodWait warnings**

### **For Quality**
1. Focus on **course quality** over quantity
2. Use **longer delays** for better user experience
3. **Batch similar courses** together
4. **Monitor user engagement**

## 🔧 **Troubleshooting**

### **If Posts Are Too Slow**
```bash
/ratelimit delay 2     # Reduce delay
/ratelimit maxpm 25    # Increase per-minute limit
```

### **If Getting FloodWait Errors**
```bash
/ratelimit delay 5     # Increase delay
/ratelimit maxpm 15    # Reduce per-minute limit
/ratelimit burst 3     # Reduce burst limit
```

### **Emergency Stop**
```bash
/ratelimit maxpm 1     # Severely limit posting
```

## ✅ **Current Status**

Your bot now has **professional-grade rate limiting** that:
- **🛡️ Prevents FloodWait errors**
- **⚡ Optimizes posting speed**
- **📊 Provides real-time monitoring**
- **🎛️ Allows dynamic adjustment**
- **🔄 Works automatically 24/7**

The rate limiter ensures your bot stays within Telegram's limits while maximizing posting efficiency!
