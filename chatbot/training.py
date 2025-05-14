import google.generativeai as genai
import textwrap
import os
from flask import Flask, request, jsonify
from flask_cors import CORS
from dotenv import load_dotenv

# Load environment variables from .env file
load_dotenv()

# ===== CONFIGURATION =====
GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
# For XAMPP localhost URLs (adjust these as needed)
BOOKING_URL = os.getenv("BOOKING_URL", "http://localhost/medicare/appointment.php")
PHARMACY_URL = os.getenv("PHARMACY_URL", "http://localhost/medicare/epharmacy.php")

if not GOOGLE_API_KEY:
    print("❌ WARNING: GOOGLE_API_KEY environment variable not set.")
    exit("API Key not configured.")

MODEL_NAME = "gemini-1.5-pro-latest"

# ===== INITIALIZATION =====
try:
    genai.configure(api_key=GOOGLE_API_KEY)
except Exception as e:
    print(f"❌ Error configuring GenerativeAI: {e}")
    exit(1)

# ===== MODEL INITIALIZATION =====
def initialize_model():
    try:
        generation_config = {
            "temperature": 0.9,
            "top_p": 1,
            "top_k": 1,
            "max_output_tokens": 2048,
        }
        safety_settings = [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
        ]
        model = genai.GenerativeModel(
            model_name=MODEL_NAME,
            generation_config=generation_config,
            safety_settings=safety_settings
        )
        print("✅ Gemini Model Initialized")
        return model
    except Exception as e:
        print(f"❌ Error initializing model: {e}")
        return None

model = initialize_model()
if not model:
    exit("❌ Exiting due to model initialization failure.")

# ===== INTENT DETECTION =====
def detect_intent(question):
    question_lower = question.lower().strip()
    
    appointment_keywords = [
        "book appointment", "schedule visit", "make appointment",
        "see doctor", "consult doctor", "set meeting",
        "medical appointment", "doctor booking", "need appointment",
        "want to see doctor", "doctor visit", "appointment"
    ]
    
    medicine_keywords = [
        "buy medicine", "order drugs", "purchase medicine",
        "get medication", "need pills", "get prescription",
        "pharmacy order", "refill meds", "order medication",
        "need drugs", "want medicine", "medicine", "drugs"
    ]
    
    if any(keyword in question_lower for keyword in appointment_keywords):
        return "appointment"
    
    if any(keyword in question_lower for keyword in medicine_keywords):
        return "medicine"
    
    return None

# ===== RESPONSE GENERATION =====
def generate_response(question):
    if not model:
        return "Model is not initialized."
    
    intent = detect_intent(question)
    
    if intent == "appointment":
        return {
            "type": "link",
            "message": "You can book your appointment here:",
            "url": BOOKING_URL,
            "text": "Book Appointment Now"
        }
    
    if intent == "medicine":
        return {
            "type": "link",
            "message": "You can order your medicine here:",
            "url": PHARMACY_URL,
            "text": "Order Medicine Now"
        }
    
    try:
        prompt = textwrap.dedent(f"""\
        You are a medical information assistant AI.
        **Instructions:**
        - Be extremely concise (2-3 sentences max)
        - Use simple language
        - Use lists when appropriate
        - Include disclaimer
        
        **Question:**
        {question}

        **Answer:**""")

        response = model.generate_content(prompt)
        
        if not response.parts:
            return {"type": "text", "content": "Sorry, I couldn't generate a response."}
            
        response_text = response.text.replace('* ', '- ')
        return {"type": "text", "content": response_text}
        
    except Exception as e:
        print(f"Error: {e}")
        return {"type": "text", "content": "Sorry, an error occurred."}

# ===== FLASK SERVER CONFIGURATION =====
app = Flask(__name__)
CORS(app)  # Enable CORS for your XAMPP frontend

@app.route('/chat', methods=['POST'])
def chat_endpoint():
    try:
        data = request.get_json()
        if not data or 'message' not in data:
            return jsonify({'error': 'Invalid request'}), 400
        
        user_message = data['message']
        print(f"User message: {user_message}")
        
        response = generate_response(user_message)
        return jsonify(response)

    except Exception as e:
        print(f"Server error: {e}")
        return jsonify({'error': 'Server error'}), 500

# ===== XAMPP-SPECIFIC SETTINGS =====
if __name__ == "__main__":
    print("🚀 Starting Medical Chatbot API for XAMPP")
    print(f"• Booking Page: {BOOKING_URL}")
    print(f"• Pharmacy Page: {PHARMACY_URL}")
    
    # Run on a different port than XAMPP (Apache typically uses 80)
    # Access this API from your XAMPP PHP files at http://localhost:5000/chat
    app.run(host='localhost', port=5000, debug=True)