import * as React from "react"
import { useState } from "react"
import { Input } from "./ui/input"


interface InputPlateProps {
  onPlateInput: (plate: string) => void;
  value?: string;
  disabled?: boolean;
}

function InputPlate({ onPlateInput, value, disabled = false }: InputPlateProps & { disabled?: boolean }) {
  const [part1, setPart1] = useState("");
  const [part2, setPart2] = useState("");
  const [part3, setPart3] = useState("");

  // Sync with value prop
  React.useEffect(() => {
    if (value) {
      const parts = value.split('-');
      setPart1(parts[0] || "");
      setPart2(parts[1] || "");
      setPart3(parts[2] || "");
    }
  }, [value]);

  const updatePlateInput = (newPart1: string, newPart2: string, newPart3: string) => {
    const plate = `${newPart1}-${newPart2}-${newPart3}`;
    onPlateInput(plate);
  };

  const handlePart1Change = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value.toUpperCase();
    setPart1(value);
    updatePlateInput(value, part2, part3);
  };

  const handlePart2Change = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value.toUpperCase();
    setPart2(value);
    updatePlateInput(part1, value, part3);
  };

  const handlePart3Change = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value.toUpperCase();
    setPart3(value);
    updatePlateInput(part1, part2, value);
  };

  return (
    <div className="rounded-lg bg-white shadow-lg">
      <div className="flex w-full rounded-xl border-grey border-1 bg-white items-center justify-center">
        <label className="flex flex-col items-center justify-between bg-blue-700 rounded-l-lg p-2 font-bold text-white">
          <img className="h-5" src="https://cdn.cdnlogo.com/logos/e/51/eu.svg" />
          <div className="text-sm text-center w-full">F</div>
        </label>
        <div className="flex flex-row w-full justify-center items-center">
          <Input
            className="items-center w-16 self-center p-0 border-1 rounded-none rounded-l-md text-center font-bold text-xl sm:text-2xl md:text-3xl uppercase"
            placeholder="AB"
            value={part1}
            onChange={handlePart1Change}
            disabled={disabled}
          />
          <div className="items-center self-center text-center font-bold text-gray-600 text-2xl"> - </div>
          <Input
            className="items-center w-16 self-center p-0 border-1 rounded-none text-center font-bold text-xl sm:text-2xl md:text-3xl uppercase text-black"
            placeholder="123"
            value={part2}
            onChange={handlePart2Change}
            disabled={disabled}
          />
          <div className="items-center self-center text-center font-bold text-gray-600 text-2xl"> - </div>
          <Input
            className="text-xl sm:text-2xl md:text-3xl items-center w-16 self-center p-0 border-1 rounded-none rounded-r-md text-center font-bold uppercase text-black"
            placeholder="CD"
            value={part3}
            onChange={handlePart3Change}
            disabled={disabled}
          />
        </div>
        <label className="flex flex-col justify-between bg-blue-700 rounded-r-lg p-2 text-sm font-bold text-white">
          <div className="flex h-5 items-stretch w-5">
            <div className="bg-blue-800 flex-1"></div>
            <div className="bg-white flex-1"></div>
            <div className="bg-red-700 flex-1"></div>
          </div>
          <div className="text-sm text-center w-full">00</div>
        </label>
      </div>
    </div>
  );
}

export { InputPlate };