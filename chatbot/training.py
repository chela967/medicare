import google.generativeai as genai
import json
import textwrap
import os # For environment variables
from flask import Flask, request, jsonify
from flask_cors import CORS # Import CORS

# ===== CONFIGURATION =====
# IMPORTANT: Load API key securely, NOT hardcoded! Use environment variables.
# Example: Set environment variable GOOGLE_API_KEY before running
GOOGLE_API_KEY = os.getenv("AIzaSyBU0nYJ79vuTX5CbJReS43Ygz96l_zrpgs")
if not GOOGLE_API_KEY:
    # Fallback for testing only - replace with your key if needed, but avoid committing it
    # GOOGLE_API_KEY = "AIzaSy..." # Replace YOUR_KEY_HERE_BUT_USE_ENV_VAR
    print("❌ WARNING: GOOGLE_API_KEY environment variable not set. Using fallback/placeholder.")
    # You might want to exit here if the key is essential: exit("API Key not configured.")

# Training file loading might not be directly applicable for simple API calls,
# unless you load it once globally for context or modify generate_response
# TRAINING_FILE = "fine_tune_data.jsonl"
MODEL_NAME = "gemini-1.5-pro-latest"

# ===== INITIALIZATION =====
try:
    genai.configure(api_key=GOOGLE_API_KEY)
except Exception as e:
     print(f"❌ Error configuring GenerativeAI: {e}")
     # Handle missing API key or configuration errors appropriately
     exit(1) # Exit if configuration fails

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

# --- Initialize the model globally when the app starts ---
model = initialize_model()
if not model:
     print("❌ Exiting due to model initialization failure.")
     exit(1)


# ===== RESPONSE GENERATION =====
# Inside the generate_response function in chatbot_api.py

# Inside the generate_response function in chatbot_api.py

def generate_response(question):
    if not model: return "Model is not initialized."
    try:
        # --- PROMPT MODIFIED FOR EXTREME BREVITY ---
        prompt = textwrap.dedent(f"""\
        You are a medical information assistant AI.
        **Instructions for your response:**
        - Provide EXTREMELY concise answers (MAXIMUM 2-3 short sentences or bullet points total).
        - Use simple, everyday language. Avoid jargon.
        - Use numbered lists (1., 2.) or dash lists (- ). Do NOT use asterisks (*).
        - Focus ONLY on the most common symptoms OR primary actions unless asked for more detail. Do not list everything.
        - ALWAYS include this exact disclaimer at the very end: "Disclaimer: This information is not a substitute for professional medical advice. Always consult a qualified healthcare provider."

        **User's Question:**
        {question}

        **Your Very Brief Answer:**""") # Changed "Answer:" to "Your Very Brief Answer:"
        # --- END MODIFIED PROMPT ---

        response = model.generate_content(prompt)
        # ... (rest of the error checking and response processing remains the same) ...
        if not response.parts:
             # ... error handling ...
             return "..." # Appropriate error/blocked message

        response_text = response.text.replace('* ', '- ')
        return response_text
    except Exception as e:
        # ... error handling ...
        return "Sorry, an error occurred while generating the response."

# ===== FLASK WEB SERVER SETUP =====
app = Flask(__name__)
CORS(app) # Enable CORS for all routes, allowing requests from your PHP frontend

@app.route('/chat', methods=['POST']) # Define the API endpoint URL and method
def chat_endpoint():
    try:
        # Get the message from the JSON data sent by JavaScript
        data = request.get_json()
        if not data or 'message' not in data:
             print("⚠️ Received invalid request data:", data)
             return jsonify({'error': 'Missing "message" in request body'}), 400 # Bad Request

        user_message = data['message']
        print(f"Received message: {user_message}") # Log received message

        # Generate the response using the Gemini model
        bot_reply = generate_response(user_message)
        print(f"Sending reply: {bot_reply}") # Log reply

        # Return the response as JSON
        return jsonify({'reply': bot_reply})

    except Exception as e:
        print(f"❌ Error in /chat endpoint: {e}")
        # Consider logging the full error e
        return jsonify({'error': 'An internal server error occurred'}), 500 # Internal Server Error


# ===== RUN THE FLASK SERVER =====
if __name__ == "__main__":
    print("🚀 Starting Flask server for Chatbot API...")
    # Use 0.0.0.0 to make it accessible on your network if needed,
    # otherwise 127.0.0.1 is safer for local development.
    # Default Flask port is 5000.
    app.run(host='127.0.0.1', port=5000, debug=True) # debug=True is helpful for development