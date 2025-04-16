import requests
import time
import sys
from typing import Optional

# ===== CONFIGURATION =====
API_KEY = "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"  # Replace with actual key
MODEL = "anthropic/claude-3-haiku"  # Fast and accurate for medical
API_URL = "https://openrouter.ai/api/v1/chat/completions"
MAX_RETRIES = 3  # Number of retry attempts
RETRY_DELAY = 2  # Seconds between retries

# ===== ENHANCED ERROR HANDLING =====
def handle_api_error(e: Exception) -> str:
    """Generate user-friendly error messages"""
    if isinstance(e, requests.exceptions.HTTPError):
        if e.response.status_code == 401:
            return "Authentication failed. Please check your API key."
        elif e.response.status_code == 429:
            return "Too many requests. Please wait a moment."
        else:
            return f"API error: {e.response.status_code}"
    elif isinstance(e, requests.exceptions.Timeout):
        return "Request timed out. Check your connection."
    else:
        return "Temporary service issue. Please try again."

# ===== ROBUST API CALL =====
def get_medical_answer(question: str) -> Optional[str]:
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "HTTP-Referer": "https://yourmedicalapp.com",
        "X-Title": "Medical Chatbot v2.0"
    }
    
    payload = {
        "model": MODEL,
        "messages": [
            {
                "role": "system",
                "content": """Respond as Dr. Bot:
1. Start with "Based on current guidelines:"
2. Use bullet points for clarity
3. End with "[Source: <authority>]"
4. Include "Consult your physician" disclaimer"""
            },
            {"role": "user", "content": question}
        ],
        "temperature": 0.2,
        "max_tokens": 500
    }

    for attempt in range(MAX_RETRIES):
        try:
            response = requests.post(
                API_URL,
                headers=headers,
                json=payload,
                timeout=10
            )
            response.raise_for_status()
            return response.json()["choices"][0]["message"]["content"].strip()
            
        except Exception as e:
            if attempt == MAX_RETRIES - 1:  # Final attempt failed
                print(f"\n⚠️ Attempt {attempt + 1} failed: {handle_api_error(e)}")
                return None
            time.sleep(RETRY_DELAY)

# ===== CHAT INTERFACE =====
def print_typing(text: str, speed: float = 0.03):
    """Enhanced typing effect with error handling"""
    print("\n\033[94m🩺 Dr. Bot:\033[0m ", end="")
    for char in text:
        try:
            sys.stdout.write(char)
            sys.stdout.flush()
            time.sleep(speed)
        except:
            break  # Handle abrupt exits gracefully
    print()

def chat_session():
    print("\n\033[92m=== Secure Medical Chat ===\033[0m")
    print("Type 'quit' anytime to exit\n")
    
    while True:
        try:
            user_input = input("\033[96m👤 Patient:\033[0m ").strip()
            if user_input.lower() in ('quit', 'exit'):
                print("\n\033[92mSession concluded. Wishing you good health!\033[0m")
                break
                
            if not user_input:
                continue
                
            print_typing("Analyzing your query...")
            
            if answer := get_medical_answer(user_input):
                print_typing(answer)
            else:
                print_typing("I'm unable to retrieve medical information at this time. Please check your connection or try later.")
                
        except KeyboardInterrupt:
            print("\n\033[91mSession terminated unexpectedly.\033[0m")
            break

if __name__ == "__main__":
    chat_session()