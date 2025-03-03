import requests

# IP-adres van de Shelly Plug
IP_ADDRESS = "http://192.168.0.250"  # Vervang dit met het juiste IP van jouw Shelly Plug

# Functie om de plug aan of uit te zetten
def control_shelly_plug(state):
    url = f"{IP_ADDRESS}/relay/0?turn={state}"
    response = requests.get(url)
    if response.status_code == 200:
        print(f"Shelly Plug is {state}")
    else:
        print("Er is een fout opgetreden bij het aansteken van de Shelly Plug.")

# Vraag de gebruiker om de status in te voeren
while True:
    command = input("Typ '1' om de Shelly Plug aan te zetten, typ '0' om de Shelly Plug uit te zetten, of 'exit' om te stoppen: ")
    if command == "1":
        control_shelly_plug("on")
    elif command == "0":
        control_shelly_plug("off")
    elif command == "exit":
        break
    else:
        print("Ongeldig commando.")
