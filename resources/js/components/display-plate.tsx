import * as React from "react"

interface DisplayPlateProps {
  value: string;
}

export function DisplayPlate({ value }: DisplayPlateProps) {
  const [part1, part2, part3] = value ? value.split('-') : ["", "", ""];
  return (
    <div className="rounded-lg bg-white shadow-lg">
      <div className="flex w-full rounded-xl border-grey border-1 bg-white items-center justify-center">
        <label className="flex flex-col items-center justify-between bg-blue-700 rounded-l-lg p-2 font-bold text-white">
          <img className="h-5" src="https://cdn.cdnlogo.com/logos/e/51/eu.svg" />
          <div className="text-sm text-center w-full">F</div>
        </label>
        <div className="flex flex-row w-full justify-center items-center">
          <div className="items-center w-16 self-center p-0 rounded-none rounded-l-md text-center font-bold text-xl sm:text-2xl md:text-3xl uppercase text-black">
            {part1}
          </div>
          <div className="items-center self-center text-center font-bold text-2xl"> - </div>
          <div className="items-center w-16 self-center p-0 rounded-none text-center font-bold text-xl sm:text-2xl md:text-3xl uppercase text-black bg-transparent">
            {part2}
          </div>
          <div className="items-center self-center text-center font-bold text-2xl"> - </div>
          <div className="text-xl sm:text-2xl md:text-3xl items-center w-16 self-center p-0 rounded-none rounded-r-md text-center font-bold uppercase text-black bg-transparent">
            {part3}
          </div>
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
