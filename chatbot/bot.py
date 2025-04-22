import google.generativeai as genai
import json
import textwrap

# ===== CONFIGURATION =====
GOOGLE_API_KEY = "AIzaSyBU0nYJ79vuTX5CbJReS43Ygz96l_zrpgs"  # Replace with your actual key
TRAINING_FILE = "fine_tune_data.jsonl"  # Your training data file
MODEL_NAME = "gemini-1.5-pro-latest"  # Current recommended model

# ===== INITIALIZATION =====
genai.configure(api_key=GOOGLE_API_KEY)

# ===== DATA PREPARATION =====
def prepare_training_data():
    try:
        with open(TRAINING_FILE, 'r', encoding='utf-8') as f:
            examples = [json.loads(line) for line in f if line.strip()]
        
        training_examples = []
        for example in examples:
            if "messages" in example:  # ChatGPT format
                training_examples.append(example["messages"])
            elif "prompt" in example and "completion" in example:  # Fine-tune format
                training_examples.append([
                    {"role": "user", "content": example["prompt"]},
                    {"role": "assistant", "content": example["completion"]}
                ])
            else:  # Generic format
                training_examples.append([
                    {"role": "user", "content": str(example)},
                    {"role": "assistant", "content": ""}
                ])
        
        print(f"✅ Loaded {len(training_examples)} training examples")
        return training_examples
    except Exception as e:
        print(f"❌ Error loading training data: {e}")
        return None

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
        return model
    except Exception as e:
        print(f"❌ Error initializing model: {e}")
        return None

def generate_response(model, context, question):
    try:
        prompt = textwrap.dedent(f"""\
        You are a medical assistant. Provide very concise answers (3-5 lines max).
        Use bullet points when listing treatments.
        Skip explanations of causes unless asked.
        
        Question: {question}
        
        Answer briefly:""")
        
        response = model.generate_content(prompt)
        return response.text
    except Exception as e:
        print(f"❌ Error: {e}")
        return None
    
# ===== MAIN APPLICATION =====
if __name__ == "__main__":
    print("=== Medical QA Assistant ===")
    print("Initializing...\n")
    
    # Prepare data
    training_data = prepare_training_data()
    if not training_data:
        exit(1)
    
    # Initialize model
    model = initialize_model()
    if not model:
        exit(1)
    
    # Interactive session
    print("Enter medical questions (type 'quit' to exit)\n")
    while True:
        try:
            question = input("Question: ").strip()
            if question.lower() in ('quit', 'exit'):
                break
            
            # Use last 2 examples as context
            context = training_data[-2:] if len(training_data) > 2 else training_data
            
            # Generate response
            response = generate_response(model, context, question)
            
            if response:
                print("\nAssistant:")
                print(textwrap.fill(response, width=80))
            else:
                print("Sorry, I couldn't generate a response.")
            
            print()  # Add blank line
            
        except KeyboardInterrupt:
            break
        except Exception as e:
            print(f"Error: {e}")